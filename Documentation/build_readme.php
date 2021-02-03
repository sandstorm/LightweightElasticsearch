<?php

chdir(__DIR__);
exec('diff -u ./01_BasicSearchTemplate.fusion ./02_FacetedSearchTemplate.fusion | tail -n +3 > ./02_FacetedSearchTemplate.fusion.diff');
exec('diff -u ./02_FacetedSearchTemplate.fusion ./03_ExternalDataTemplate.fusion | tail -n +3 > ./03_ExternalDataTemplate.fusion.diff');

$readme = file_get_contents(__DIR__ .'/README_template.md');

$readme = str_replace('###01_BasicSearchTemplate.fusion###', file_get_contents(__DIR__ . '/01_BasicSearchTemplate.fusion'), $readme);
$readme = str_replace('###02_FacetedSearchTemplate.fusion###', file_get_contents(__DIR__ . '/02_FacetedSearchTemplate.fusion'), $readme);
$readme = str_replace('###02_FacetedSearchTemplate.fusion.diff###', file_get_contents(__DIR__ . '/02_FacetedSearchTemplate.fusion.diff'), $readme);
$readme = str_replace('###03_CommandController.php###', file_get_contents(__DIR__ . '/03_CommandController.php'), $readme);
$readme = str_replace('###03_ExternalDataTemplate.fusion###', file_get_contents(__DIR__ . '/03_ExternalDataTemplate.fusion'), $readme);
$readme = str_replace('###03_ExternalDataTemplate.fusion.diff###', file_get_contents(__DIR__ . '/03_ExternalDataTemplate.fusion.diff'), $readme);
$readme = str_replace('###03_FulltextEelHelper.php###', file_get_contents(__DIR__ . '/03_FulltextEelHelper.php'), $readme);


file_put_contents(__DIR__ . '/../README.md', $readme);
