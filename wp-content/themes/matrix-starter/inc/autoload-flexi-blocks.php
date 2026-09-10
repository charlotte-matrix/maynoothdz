<?php
// Add this to your theme's functions.php file

use StoutLogic\AcfBuilder\FieldsBuilder;

if (function_exists('acf_add_local_field_group')) {

    // Define the Flexible Content Field Group
    $flexible_content = new FieldsBuilder('flexible_content_blocks');

    $flexible_content
        ->setLocation('post_type', '==', 'page')
        ->addFlexibleContent('flexible_content_blocks', [
            'label' => 'Flexible Content Blocks',
            'button_label' => 'Add Block',
        ])
        ->addLayout('intro_001', [
            'label' => 'Intro Block',
            'sub_fields' => $intro_001->build(), // Ensure this returns the correct fields
        ])
        ->endFlexibleContent();

    acf_add_local_field_group($flexible_content->build());

    // Finalize and register the flexible content field group
    acf_add_local_field_group($flexible_content->build());
}
