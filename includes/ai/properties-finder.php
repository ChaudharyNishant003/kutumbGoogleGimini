<?php
/** Compact Home Finder on properties.php — its chips mirror the filters actually applied. Uses $filters and $total. */
$hf_variant = 'compact';
$hf_filters = $filters;
$hf_total = $total;
include __DIR__ . '/home-finder.php';
