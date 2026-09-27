<?php

declare(strict_types=1);

namespace App\Page;

use App\Api\ApiValidationException;
use App\Enum\PageTemplate;
use App\Repository\LeadCategoryRepository;
use App\Repository\MediaAssetRepository;
use Symfony\Component\String\Slugger\AsciiSlugger;

/**
 * Checks a page's content against its template, and returns it clean (strings trimmed, unknown keys dropped, form
 * field keys filled in). Every problem is reported at once, on the path the editor shows it at
 * ("sections[2].fields.items[0].title", "form.fields[1].options").
 *
 * Only an enabled section must be complete: a section switched off may be left half written.
 */
final class ContentValidator
{
    public const FIELD_TYPES = ['text', 'textarea', 'select', 'checkbox', 'number'];
    public const MAX_EXTRA_FIELDS = 10;
    /** Keys the form always has; an extra field cannot take them. */
    public const FIXED_KEYS = ['name', 'email', 'phone', 'consent', 'website', '_t'];

    /** @var list<array{field: string, message: string, parameters?: array<string, string|int>}> */
    private array $violations = [];

    public function __construct(
        private readonly MediaAssetRepository $media,
        private readonly LeadCategoryRepository $categories,
    ) {
    }

    /**
     * @param array<string, mixed> $content
     *
     * @return array<string, mixed> the content, clean
     *
     * @throws ApiValidationException with every violation
     */
    public function validate(PageTemplate $template, array $content): array
    {
        $this->violations = [];

        $clean = [
            'sections' => $this->sections($template, $content['sections'] ?? null),
            'form' => ['fields' => $this->formFields($content['form']['fields'] ?? [])],
            'seo' => $this->seo(\is_array($content['seo'] ?? null) ? $content['seo'] : []),
            'settings' => $this->settings(\is_array($content['settings'] ?? null) ? $content['settings'] : []),
        ];
        $this->checkImages($clean);
        $this->checkCategories($clean);

        if ([] !== $this->violations) {
            throw new ApiValidationException($this->violations);
        }

        return $clean;
    }

    /**
     * What publishing asks beyond a valid draft: a lead magnet has the link it promises.
     *
     * @param array<string, mixed> $content
     */
    public function assertPublishable(PageTemplate $template, array $content): void
    {
        if (PageTemplate::LeadMagnet !== $template) {
            return;
        }
        foreach ($content['sections'] as $index => $section) {
            if ('form' === $section['type'] && '' === ($section['fields']['resourceUrl'] ?? '')) {
                throw ApiValidationException::single("sections[$index].fields.resourceUrl", 'Add the link the visitor will receive.');
            }
        }
    }

    /**
     * @return list<array{id: string, type: string, enabled: bool, fields: array<string, mixed>}>
     */
    private function sections(PageTemplate $template, mixed $sections): array
    {
        $expected = [];
        foreach (TemplateCatalog::sections($template) as $section) {
            $expected[$section['id']] = $section['type'];
        }

        $given = \is_array($sections) ? array_values($sections) : [];
        $ids = array_map(static fn (mixed $s) => \is_array($s) ? ($s['id'] ?? null) : null, $given);
        $sorted = $ids;
        sort($sorted);
        $expectedIds = array_keys($expected);
        sort($expectedIds);
        if ($sorted !== $expectedIds) {
            $this->fail('sections', 'The sections must be the ones of the page template.');

            return [];
        }

        $clean = [];
        foreach ($given as $index => $section) {
            $type = $expected[$section['id']];
            $enabled = true === ($section['enabled'] ?? true);
            $fields = \is_array($section['fields'] ?? null) ? $section['fields'] : [];
            $clean[] = [
                'id' => $section['id'],
                'type' => $type,
                'enabled' => $enabled,
                'fields' => $this->fields(TemplateCatalog::SECTION_TYPES[$type], $fields, "sections[$index].fields", $enabled),
            ];
        }

        return $clean;
    }

    /**
     * @param array<string, array<string, mixed>> $specs
     * @param array<string, mixed>                $values
     *
     * @return array<string, mixed>
     */
    private function fields(array $specs, array $values, string $path, bool $required): array
    {
        $clean = [];
        foreach ($specs as $name => $spec) {
            $value = $values[$name] ?? null;
            $at = "$path.$name";
            $clean[$name] = match ($spec['kind']) {
                'items' => $this->items($spec, $value, $at, $required),
                'image' => $this->imageId($value, $at),
                'date' => $this->date($value, $at),
                default => $this->text($spec, $value, $at, $required),
            };
        }

        return $clean;
    }

    /**
     * @param array<string, mixed> $spec
     *
     * @return list<array<string, mixed>>
     */
    private function items(array $spec, mixed $value, string $path, bool $required): array
    {
        $items = \is_array($value) ? array_values($value) : [];
        if (\count($items) > $spec['maxItems']) {
            $this->fail($path, 'At most %max% items.', ['%max%' => $spec['maxItems']]);
            $items = \array_slice($items, 0, $spec['maxItems']);
        }

        return array_map(
            fn (mixed $item, int $index) => $this->fields($spec['fields'], \is_array($item) ? $item : [], "{$path}[$index]", $required),
            $items,
            array_keys($items),
        );
    }

    /**
     * @param array<string, mixed> $spec
     */
    private function text(array $spec, mixed $value, string $path, bool $required): string
    {
        if (null !== $value && !\is_string($value)) {
            $this->fail($path, 'This value should be of type %type%.', ['%type%' => 'string']);

            return '';
        }
        $text = trim((string) $value);
        if ($required && ($spec['required'] ?? false) && '' === $text) {
            $this->fail($path, 'Fill in this field.');
        }
        if (isset($spec['max']) && mb_strlen($text) > $spec['max']) {
            $this->fail($path, 'Use at most %max% characters.', ['%max%' => $spec['max']]);
        }
        if ('url' === $spec['kind'] && '' !== $text && 1 !== preg_match('#^https?://[^\s/$.?\#].[^\s]*$#i', $text)) {
            $this->fail($path, 'Enter a web address that starts with http:// or https://.');
        }

        return $text;
    }

    private function imageId(mixed $value, string $path): ?string
    {
        if (null === $value || '' === $value) {
            return null;
        }
        if (!\is_string($value)) {
            $this->fail($path, 'Choose an image from your library.');

            return null;
        }

        return $value;
    }

    private function date(mixed $value, string $path): ?string
    {
        if (null === $value || '' === $value) {
            return null;
        }
        $date = \is_string($value) ? \DateTimeImmutable::createFromFormat('!Y-m-d', $value) : false;
        if (false === $date || $date->format('Y-m-d') !== $value) {
            $this->fail($path, 'Enter a real date.');

            return null;
        }

        return $value;
    }

    /**
     * @return list<array{key: string, label: string, type: string, required: bool, options: list<string>, optionCategories: array<string, string>}>
     */
    private function formFields(mixed $fields): array
    {
        $fields = \is_array($fields) ? array_values($fields) : [];
        if (\count($fields) > self::MAX_EXTRA_FIELDS) {
            $this->fail('form.fields', 'At most %max% extra fields.', ['%max%' => self::MAX_EXTRA_FIELDS]);
            $fields = \array_slice($fields, 0, self::MAX_EXTRA_FIELDS);
        }

        $slugger = new AsciiSlugger('es');
        $clean = [];
        $keys = [];
        foreach ($fields as $index => $field) {
            $field = \is_array($field) ? $field : [];
            $path = "form.fields[$index]";
            $label = $this->text(['kind' => 'text', 'max' => 80, 'required' => true], $field['label'] ?? null, "$path.label", true);
            $type = \is_string($field['type'] ?? null) ? $field['type'] : '';
            if (!\in_array($type, self::FIELD_TYPES, true)) {
                $this->fail("$path.type", 'Choose one of the field types offered.');
            }
            // A field keeps its key once it has one, so answers already received stay attached to it.
            $key = \is_string($field['key'] ?? null) && '' !== $field['key'] ? $field['key'] : strtolower((string) $slugger->slug($label, '_'));
            $key = substr(preg_replace('/[^a-z0-9_]/', '', $key) ?? '', 0, 40);
            if ('' === $key || \in_array($key, self::FIXED_KEYS, true) || \in_array($key, $keys, true)) {
                $key = 'campo_'.($index + 1);
            }
            $keys[] = $key;

            $options = [];
            if ('select' === $type) {
                $given = \is_array($field['options'] ?? null) ? array_values($field['options']) : [];
                foreach ($given as $o => $option) {
                    $options[] = $this->text(['kind' => 'text', 'max' => 80, 'required' => true], $option, "$path.options[$o]", true);
                }
                if ([] === $options) {
                    $this->fail("$path.options", 'A list needs at least one option.');
                } elseif (\count($options) > 20) {
                    $this->fail("$path.options", 'At most %max% options.', ['%max%' => 20]);
                } elseif (\count(array_unique($options)) !== \count($options)) {
                    $this->fail("$path.options", 'Each option must be different.');
                }
            }

            $optionCategories = [];
            foreach ((\is_array($field['optionCategories'] ?? null) ? $field['optionCategories'] : []) as $option => $categoryId) {
                if (\in_array($option, $options, true) && \is_string($categoryId) && '' !== $categoryId) {
                    $optionCategories[(string) $option] = $categoryId;
                }
            }

            $clean[] = [
                'key' => $key,
                'label' => $label,
                'type' => $type,
                'required' => true === ($field['required'] ?? false),
                'options' => $options,
                'optionCategories' => $optionCategories,
            ];
        }

        return $clean;
    }

    /**
     * @param array<string, mixed> $seo
     *
     * @return array{title: string, description: string, imageId: string|null, index: bool}
     */
    private function seo(array $seo): array
    {
        return [
            'title' => $this->text(['kind' => 'text', 'max' => 70, 'required' => true], $seo['title'] ?? null, 'seo.title', true),
            'description' => $this->text(['kind' => 'textarea', 'max' => 160], $seo['description'] ?? null, 'seo.description', true),
            'imageId' => $this->imageId($seo['imageId'] ?? null, 'seo.imageId'),
            'index' => false !== ($seo['index'] ?? true),
        ];
    }

    /**
     * @param array<string, mixed> $settings
     *
     * @return array{defaultCategoryId: string|null, accent: string}
     */
    private function settings(array $settings): array
    {
        $accent = $settings['accent'] ?? 'navy';
        if (!\is_string($accent) || !isset(TemplateCatalog::ACCENTS[$accent])) {
            $this->fail('settings.accent', 'Choose one of the colours offered.');
            $accent = 'navy';
        }
        $category = $settings['defaultCategoryId'] ?? null;

        return ['defaultCategoryId' => \is_string($category) && '' !== $category ? $category : null, 'accent' => $accent];
    }

    /**
     * Every image the content points at is an active one of this consultant's library.
     *
     * @param array<string, mixed> $content
     */
    private function checkImages(array $content): void
    {
        $refs = [];
        foreach ($content['sections'] as $index => $section) {
            foreach (TemplateCatalog::SECTION_TYPES[$section['type']] as $name => $spec) {
                if ('image' === $spec['kind'] && null !== $section['fields'][$name]) {
                    $refs["sections[$index].fields.$name"] = $section['fields'][$name];
                }
            }
        }
        if (null !== $content['seo']['imageId']) {
            $refs['seo.imageId'] = $content['seo']['imageId'];
        }
        $found = $this->media->findActiveByIds(array_values($refs));
        foreach ($refs as $path => $id) {
            if (!isset($found[$id])) {
                $this->fail($path, 'Choose an image from your library.');
            }
        }
    }

    /**
     * Every category the content points at is one of this consultant's.
     *
     * @param array<string, mixed> $content
     */
    private function checkCategories(array $content): void
    {
        $refs = [];
        if (null !== $content['settings']['defaultCategoryId']) {
            $refs['settings.defaultCategoryId'] = $content['settings']['defaultCategoryId'];
        }
        foreach ($content['form']['fields'] as $index => $field) {
            foreach ($field['optionCategories'] as $categoryId) {
                $refs["form.fields[$index].optionCategories"] = $categoryId;
            }
        }
        $found = [];
        foreach ($this->categories->findByIds(array_values(array_unique($refs))) as $category) {
            $found[(string) $category->getId()] = true;
        }
        foreach ($refs as $path => $id) {
            if (!isset($found[$id])) {
                $this->fail($path, 'Choose one of your categories.');
            }
        }
    }

    /**
     * @param array<string, string|int> $parameters
     */
    private function fail(string $field, string $message, array $parameters = []): void
    {
        $this->violations[] = ['field' => $field, 'message' => $message, 'parameters' => $parameters];
    }
}
