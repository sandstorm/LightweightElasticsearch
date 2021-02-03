<?php
$readme = file_get_contents(__DIR__ .'/README_template.md');
$readme = str_replace('###01_BasicSearchTemplate.fusion###', file_get_contents(__DIR__ . '/01_BasicSearchTemplate.fusion'), $readme);

file_put_contents(__DIR__ . '/../README.md', $readme);
