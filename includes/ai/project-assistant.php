<?php
/** AI Project Assistant (Feature 5): a chat about THIS listing only. Expects $property. */
require_once __DIR__ . '/../ai.php';
if (!ai_settings()['enabled']) return; // switched off in Admin → Settings: the global chat stays available instead
$pa_contact = '/contact.php?property=' . (int)$property['id'];
?>
<div class="ai-pa" data-pa data-property-id="<?= (int)$property['id'] ?>" data-property-name="<?= e($property['name']) ?>">
  <button type="button" class="ai-pa-launcher" data-pa-toggle aria-expanded="false" aria-controls="ai-pa-panel">
    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M21 11.5a8.4 8.4 0 0 1-9 8.5 8.4 8.4 0 0 1-3.8-.9L3 21l1.9-5.2A8.4 8.4 0 0 1 12 3a8.4 8.4 0 0 1 9 8.5z"/></svg>
    <span>Ask about this project</span>
  </button>

  <div class="ai-pa-panel" id="ai-pa-panel" role="dialog" aria-modal="false" aria-labelledby="ai-pa-title" hidden>
    <div class="ai-pa-head">
      <span class="ai-pa-avatar" aria-hidden="true">K</span>
      <div class="ai-pa-head-text">
        <strong id="ai-pa-title"><?= e($property['name']) ?> assistant</strong>
        <span>Answers about this project only</span>
      </div>
      <button type="button" class="ai-pa-close" data-pa-close aria-label="Close assistant">×</button>
    </div>
    <div class="ai-pa-trust">Verified answers only — every fact shows its source and date.</div>

    <div class="ai-pa-log" role="log" aria-live="polite" aria-relevant="additions" data-pa-log>
      <div class="ai-bubble ai-bubble--bot">Hi! I can answer questions about <strong><?= e($property['name']) ?></strong> from its verified project information. Pick a question or type your own.</div>
    </div>

    <div class="ai-pa-suggest" data-pa-suggest>
      <button type="button" data-pa-ask="When is possession expected?">Possession timeline</button>
      <button type="button" data-pa-ask="What is the price and is it available?">Price &amp; availability</button>
      <button type="button" data-pa-ask="What configurations are available?">Configurations</button>
      <button type="button" data-pa-ask="Is it RERA registered?">RERA registration</button>
    </div>

    <form class="ai-pa-input" data-pa-form>
      <label for="ai-pa-q" class="sr-only">Ask about this project</label>
      <input type="text" id="ai-pa-q" maxlength="<?= AI_MAX_INPUT ?>" placeholder="Ask about price, possession, FAQs…" autocomplete="off">
      <button type="submit" aria-label="Send">→</button>
    </form>
    <p class="ai-pa-foot">Questions are logged to improve our project data. For anything else, <a href="<?= e($pa_contact) ?>" data-pa-contact>contact our team</a>.</p>
  </div>
</div>
