<?php
// Round-robin assignment of new leads to the sales team (admin users with the Agent role),
// plus the agent's first follow-up. Admins can always reassign from Leads afterwards.
require_once __DIR__ . '/db.php';

/** Hours after a lead arrives that the assigned agent's "first call" follow-up is due. */
const LEAD_FIRST_CALL_HOURS = 2;

function lead_auto_assign_enabled(): bool
{
    return (bool)db()->query('SELECT auto_assign_leads FROM app_settings WHERE id = 1')->fetchColumn();
}

/**
 * Picks the next active agent after the one who got the previous lead and remembers the turn.
 * Returns null when auto-assign is off or there are no active agents (the lead stays Unassigned).
 */
function lead_next_agent_id(): ?int
{
    $pdo = db();
    $pdo->beginTransaction();
    try {
        // Row lock: two leads arriving together still go to two different agents.
        $settings = $pdo->query('SELECT auto_assign_leads, last_assigned_admin_id FROM app_settings WHERE id = 1 FOR UPDATE')->fetch();
        if (!$settings || !(int)$settings['auto_assign_leads']) {
            $pdo->commit();
            return null;
        }
        $agents = $pdo->query("SELECT id FROM admin_users WHERE role = 'agent' AND active = 1 ORDER BY id")->fetchAll(PDO::FETCH_COLUMN);
        if (!$agents) {
            $pdo->commit();
            return null;
        }
        $next = (int)$agents[0];
        foreach ($agents as $id) {
            if ((int)$id > (int)$settings['last_assigned_admin_id']) {
                $next = (int)$id;
                break;
            }
        }
        $pdo->prepare('UPDATE app_settings SET last_assigned_admin_id = ? WHERE id = 1')->execute([$next]);
        $pdo->commit();
        return $next;
    } catch (Throwable $e) {
        $pdo->rollBack();
        throw $e;
    }
}

/**
 * Assigns a just-created lead to the next agent and gives that agent a "first call" follow-up.
 * Pass $savedSearchId when the lead came from a saved search, so that search gets the same owner.
 */
function lead_auto_assign(int $enquiryId, ?int $savedSearchId = null): ?int
{
    $agentId = lead_next_agent_id();
    if ($agentId === null) return null;
    lead_assign_to($enquiryId, $agentId, 'First call — new lead from the website', $savedSearchId);
    return $agentId;
}

/** Sets the lead's owner (and its saved search's owner) and adds the owner's first-call follow-up. */
function lead_assign_to(int $enquiryId, int $adminId, string $note, ?int $savedSearchId = null): void
{
    db()->prepare('UPDATE enquiries SET assigned_admin_id = ? WHERE id = ?')->execute([$adminId, $enquiryId]);
    if ($savedSearchId) {
        db()->prepare('UPDATE saved_searches SET assigned_admin_id = ? WHERE id = ?')->execute([$adminId, $savedSearchId]);
    }
    db()->prepare("INSERT INTO follow_ups (enquiry_id, assigned_admin_id, due_at, note, kind) VALUES (?, ?, ?, ?, 'callback')")
        ->execute([$enquiryId, $adminId, date('Y-m-d H:i:s', time() + LEAD_FIRST_CALL_HOURS * 3600), $note]);
}
