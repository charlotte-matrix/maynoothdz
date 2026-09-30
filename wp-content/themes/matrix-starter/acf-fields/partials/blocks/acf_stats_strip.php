<?php

use StoutLogic\AcfBuilder\FieldsBuilder;

$stats_strip = new FieldsBuilder('stats_strip', [
    'label' => 'Stats strip',
]);

$stats_strip
    ->addTab('Content', ['placement' => 'top'])
        ->addRepeater('stats', [
            'label' => 'Stats',
            'layout' => 'block',
            'button_label' => 'Add stat',
            'min' => 1,
            'max' => 6,
        ])
            ->addText('value', [
                'label' => 'Value',
                'instructions' => 'Displayed number or text (e.g. 73635, 75.2, 2023).',
                'required' => 1,
            ])
            ->addTrueFalse('animate', [
                'label' => 'Animate count-up',
                'instructions' => 'Only works when Value is numeric.',
                'ui' => 1,
                'default_value' => 1,
            ])
            ->addNumber('decimals', [
                'label' => 'Decimal places',
                'min' => 0,
                'max' => 3,
                'default_value' => 0,
            ])
                ->conditional('animate', '==', '1')
            ->addText('suffix', [
                'label' => 'Suffix',
                'instructions' => 'e.g. %',
            ])
                ->conditional('animate', '==', '1')
            ->addTextarea('label', [
                'label' => 'Label',
                'instructions' => 'Supporting text under the value. Basic HTML allowed (e.g. a source link).',
                'rows' => 2,
                'new_lines' => 'br',
            ])
        ->endRepeater()
        ->addTextarea('source_note', [
            'label' => 'Source note',
            'instructions' => 'Optional footnote under the stats (e.g. * Source: …).',
            'rows' => 2,
            'new_lines' => 'br',
        ]);

return $stats_strip;
