<?php
/** "Paste Google Maps link" box. Expects $mlLatId and $mlLngId (ids of the lat/lng inputs). */
?>
<div class="form-field maps-link-helper" data-maps-link data-lat-target="#<?= e($mlLatId) ?>" data-lng-target="#<?= e($mlLngId) ?>">
  <label for="<?= e($mlLatId) ?>-maps">Paste Google Maps link <span class="field-badge-optional">Fills location automatically</span></label>
  <input type="text" id="<?= e($mlLatId) ?>-maps" placeholder="https://www.google.com/maps/place/…  or  28.4595, 77.0266" autocomplete="off">
  <p class="maps-link-status" data-maps-status role="status" aria-live="polite"></p>
</div>
