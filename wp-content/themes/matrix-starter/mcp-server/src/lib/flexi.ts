import fs from "node:fs/promises";
import path from "node:path";
import {
  PATHS,
  THEME_ROOT,
  layoutFromAcfFilename,
  layoutFromFlexiFilename,
} from "../config.js";

function relativeThemePath(absolutePath: string): string {
  return path.relative(THEME_ROOT, absolutePath).replace(/\\/g, "/");
}

export type FlexiLayoutStatus = {
  layout: string;
  acfFile: string | null;
  templateFile: string | null;
  valid: boolean;
  issues: string[];
};

async function listPhpBasenames(dir: string): Promise<string[]> {
  try {
    const entries = await fs.readdir(dir);
    return entries.filter((name) => name.endsWith(".php")).sort();
  } catch {
    return [];
  }
}

export async function getFlexiInventory(): Promise<FlexiLayoutStatus[]> {
  const acfFiles = await listPhpBasenames(PATHS.acfBlocks);
  const templateFiles = await listPhpBasenames(PATHS.flexiTemplates);

  const acfByLayout = new Map<string, string>();
  for (const file of acfFiles) {
    const layout = layoutFromAcfFilename(file);
    if (layout) {
      acfByLayout.set(layout, file);
    }
  }

  const templateByLayout = new Map<string, string>();
  for (const file of templateFiles) {
    const layout = layoutFromFlexiFilename(file);
    if (layout) {
      templateByLayout.set(layout, file);
    }
  }

  const layouts = new Set([...acfByLayout.keys(), ...templateByLayout.keys()]);

  return [...layouts]
    .sort()
    .map((layout) => {
      const acfFile = acfByLayout.get(layout) ?? null;
      const templateFile = templateByLayout.get(layout) ?? null;
      const issues: string[] = [];

      if (!acfFile) {
        issues.push(`Missing ACF definition: acf-fields/partials/blocks/acf_${layout}.php`);
      }

      if (!templateFile) {
        issues.push(`Missing template: template-parts/flexi/${layout}.php`);
      }

      return {
        layout,
        acfFile,
        templateFile,
        valid: issues.length === 0,
        issues,
      };
    });
}

export async function validateFlexiBlocks(): Promise<{
  valid: boolean;
  layouts: FlexiLayoutStatus[];
  summary: string;
}> {
  const layouts = await getFlexiInventory();
  const invalid = layouts.filter((layout) => !layout.valid);

  const summary =
    invalid.length === 0
      ? `All ${layouts.length} flexi layout(s) have matching ACF + template files.`
      : `${invalid.length} of ${layouts.length} layout(s) are missing a paired file.`;

  return {
    valid: invalid.length === 0,
    layouts,
    summary,
  };
}

export type ScaffoldFlexiBlockInput = {
  layout: string;
  label: string;
  overwrite?: boolean;
};

export type ScaffoldFlexiBlockResult = {
  created: string[];
  skipped: string[];
};

function toLabel(layout: string): string {
  return layout
    .split("_")
    .map((part) => part.charAt(0).toUpperCase() + part.slice(1))
    .join(" ");
}

function buildAcfPartial(layout: string, label: string): string {
  return `<?php

use StoutLogic\\AcfBuilder\\FieldsBuilder;

$${layout.replace(/[^a-zA-Z0-9_]/g, "_")} = new FieldsBuilder('${layout}', [
    'label' => '${label}',
]);

$${layout.replace(/[^a-zA-Z0-9_]/g, "_")}
  ->addTab('Content', ['placement' => 'top'])
    ->addWysiwyg('text_content', [
        'label' => 'Content',
        'instructions' => 'Enter the section content.',
        'media_upload' => 0,
        'toolbar' => 'full',
    ])

  ->addTab('Layout')
    ->addRepeater('padding_settings', [
        'label' => 'Padding Settings',
        'button_label' => 'Add Screen Size Padding',
    ])
      ->addSelect('screen_size', [
          'label' => 'Screen Size',
          'choices' => [
              'xxs' => 'xxs',
              'xs' => 'xs',
              'mob' => 'mob',
              'sm' => 'sm',
              'md' => 'md',
              'lg' => 'lg',
              'xl' => 'xl',
              'xxl' => 'xxl',
              'ultrawide' => 'ultrawide',
          ],
      ])
      ->addNumber('padding_top', [
          'label' => 'Padding Top',
          'min' => 0,
          'max' => 20,
          'step' => 0.1,
          'append' => 'rem',
      ])
      ->addNumber('padding_bottom', [
          'label' => 'Padding Bottom',
          'min' => 0,
          'max' => 20,
          'step' => 0.1,
          'append' => 'rem',
      ])
    ->endRepeater();

return $${layout.replace(/[^a-zA-Z0-9_]/g, "_")};
`;
}

function buildFlexiTemplate(layout: string): string {
  return `<?php
$text_content = get_sub_field('text_content');

$padding_classes = [];
if (have_rows('padding_settings')) {
  while (have_rows('padding_settings')) {
    the_row();
    $screen = get_sub_field('screen_size');
    $pt = get_sub_field('padding_top');
    $pb = get_sub_field('padding_bottom');
    if ($screen && $pt !== null && $pt !== '') {
      $padding_classes[] = "{$screen}:pt-[{$pt}rem]";
    }
    if ($screen && $pb !== null && $pb !== '') {
      $padding_classes[] = "{$screen}:pb-[{$pb}rem]";
    }
  }
}
?>

<section class="overflow-hidden bg-white ${layout}">
  <div class="container mx-auto px-4 <?php echo esc_attr(implode(' ', $padding_classes)); ?>">
    <?php if ($text_content) : ?>
      <div class="prose max-w-none entry-content">
        <?php echo wp_kses_post($text_content); ?>
      </div>
    <?php endif; ?>
  </div>
</section>
`;
}

export async function scaffoldFlexiBlock(
  input: ScaffoldFlexiBlockInput,
): Promise<ScaffoldFlexiBlockResult> {
  const layout = input.layout.trim();
  if (!/^[a-z][a-z0-9_]*$/.test(layout)) {
    throw new Error(
      "Layout must be lowercase snake_case and start with a letter (e.g. content_002).",
    );
  }

  const label = input.label.trim() || toLabel(layout);
  const acfPath = path.join(PATHS.acfBlocks, `acf_${layout}.php`);
  const templatePath = path.join(PATHS.flexiTemplates, `${layout}.php`);
  const created: string[] = [];
  const skipped: string[] = [];

  await fs.mkdir(PATHS.acfBlocks, { recursive: true });
  await fs.mkdir(PATHS.flexiTemplates, { recursive: true });

  for (const [filePath, contents] of [
    [acfPath, buildAcfPartial(layout, label)],
    [templatePath, buildFlexiTemplate(layout)],
  ] as const) {
    try {
      await fs.access(filePath);
      if (!input.overwrite) {
        skipped.push(relativeThemePath(filePath));
        continue;
      }
    } catch {
      // file does not exist — create it
    }

    await fs.writeFile(filePath, contents, "utf8");
    created.push(relativeThemePath(filePath));
  }

  return { created, skipped };
}
