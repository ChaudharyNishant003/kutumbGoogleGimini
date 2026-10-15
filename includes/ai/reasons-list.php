<?php
/** Renders a matched / not-matched reasons list. Expects $reasons; optional $reasonsCompact. */
?>
<ul class="ai-reasons<?= !empty($reasonsCompact) ? ' ai-reasons--compact' : '' ?>">
  <?php foreach ($reasons as $reason): ?>
    <li class="<?= $reason['ok'] ? 'is-ok' : 'is-miss' ?>">
      <span class="ai-reason-icon" aria-hidden="true">
        <?php if ($reason['ok']): ?><svg width="12" height="12" viewBox="0 0 16 16" fill="none"><path d="M3 8.5L6.2 11.5L13 4.5" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>
        <?php else: ?><svg width="11" height="11" viewBox="0 0 16 16" fill="none"><path d="M4 4L12 12M12 4L4 12" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg><?php endif; ?>
      </span>
      <span><span class="sr-only"><?= $reason['ok'] ? 'Matched: ' : '' ?></span><?= e($reason['text']) ?></span>
    </li>
  <?php endforeach; ?>
</ul>
