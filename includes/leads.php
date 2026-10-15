<?php
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/auth.php';

/** Agents work only their own leads; admins and owners see everyone's. */
function lead_visible_to_current_admin(array $enquiry): bool
{
    if (admin_role_at_least('admin')) return true;
    return (int)($enquiry['assigned_admin_id'] ?? 0) === (int)(current_admin()['id'] ?? -1);
}

/**
 * SQL condition limiting a list to the current agent's own records ('' for admins/owners).
 * $column is the assigned_admin_id column of the list's main table, e.g. 'e.assigned_admin_id'.
 */
function lead_scope_sql(string $column): string
{
    if (admin_role_at_least('admin')) return '';
    return ' AND ' . $column . ' = ' . (int)(current_admin()['id'] ?? -1);
}

function lead_status_options(): array
{
    return ['new' => 'New', 'contacted' => 'Contacted', 'qualified' => 'Qualified', 'converted' => 'Converted', 'lost' => 'Lost'];
}

/**
 * Rule-based lead score, computed at read time (never cached) so it stays correct
 * as a linked property's price/featured status — or the scoring rules — change.
 * Expects an enquiry row optionally joined with price_min/featured from properties.
 */
function lead_score(array $enquiry): int
{
    $score = 0;
    if (!empty($enquiry['price_min']) && (float)$enquiry['price_min'] >= 20000000) $score += 30;
    if (!empty($enquiry['featured'])) $score += 20;
    if (!empty($enquiry['email'])) $score += 15;
    if (!empty($enquiry['message']) && mb_strlen($enquiry['message']) > 40) $score += 10;
    if (!empty($enquiry['created_at']) && strtotime($enquiry['created_at']) >= strtotime('-3 days')) $score += 10;
    return $score;
}

function lead_score_label(int $score): string
{
    if ($score >= 60) return 'Hot';
    if ($score >= 30) return 'Warm';
    return 'Cold';
}

function lead_score_class(int $score): string
{
    if ($score >= 60) return 'hot';
    if ($score >= 30) return 'warm';
    return 'cold';
}

function saved_search_status_options(): array
{
    return ['new' => 'New', 'contacted' => 'Contacted', 'matched' => 'Matched', 'closed' => 'Closed'];
}

function saved_search_timeline_label(?string $timeline): ?string
{
    $labels = ['now' => 'Within 3 months', '6m' => '3–6 months', '12m' => '6–12 months', 'research' => 'Researching for now'];
    return $labels[$timeline] ?? null;
}

/** Count of live listings matching a saved search's criteria (corridor/category/budget/config). */
function saved_search_match_count(array $search): int
{
    $sql = 'SELECT COUNT(*) FROM properties WHERE deleted_at IS NULL';
    $params = [];

    if (!empty($search['corridor_id'])) {
        $sql .= ' AND corridor_id = ?';
        $params[] = $search['corridor_id'];
    }
    if (!empty($search['category'])) {
        $sql .= ' AND category = ?';
        $params[] = $search['category'];
    }
    if (!empty($search['budget_min'])) {
        $sql .= ' AND price_min >= ?';
        $params[] = $search['budget_min'];
    }
    if (!empty($search['budget_max'])) {
        $sql .= ' AND price_min <= ?';
        $params[] = $search['budget_max'];
    }

    $stmt = db()->prepare($sql);
    $stmt->execute($params);
    return (int)$stmt->fetchColumn();
}
