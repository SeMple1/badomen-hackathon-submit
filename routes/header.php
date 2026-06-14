<?php

declare(strict_types=1);

// Keep the legacy /header route functional while sharing the current header view.
renderView('header', ['title' => 'Badomen']);
