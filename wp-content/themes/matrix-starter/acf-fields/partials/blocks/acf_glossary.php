<?php

use StoutLogic\AcfBuilder\FieldsBuilder;

$glossary = new FieldsBuilder('glossary', [
    'label' => 'Glossary / anchor',
]);

$glossary
    ->addTab('Content', ['placement' => 'top'])
        ->addText('term', ['label' => 'Term', 'required' => 1])
        ->addText('anchor_id', ['label' => 'Anchor ID', 'instructions' => 'Optional HTML id, e.g. retrofit'])
        ->addTextarea('definition', ['label' => 'Definition', 'rows' => 3, 'new_lines' => 'br'])
        ->addLink('link', ['label' => 'Optional link']);

return $glossary;
