<?php

declare(strict_types=1);

namespace App\Page;

use App\Enum\PageTemplate;

/**
 * The five landing-page templates and the section types they are made of.
 *
 * A section type says which fields it has and their limits (App\Page\ContentValidator checks drafts against them); a
 * template is a fixed list of sections, each with default Spanish content, so a new page reads as finished before
 * the consultant changes a word. The consultant turns sections on and off, reorders them and edits their fields; they
 * cannot add sections a template does not have.
 *
 * Field kinds: text (one line), textarea, image (a MediaAsset id), date (Y-m-d), url, items (a repeatable group).
 */
final class TemplateCatalog
{
    /**
     * @var array<string, array<string, array{kind: string, max?: int, required?: bool, maxItems?: int, fields?: array<string, array{kind: string, max?: int, required?: bool}>}>>
     */
    public const SECTION_TYPES = [
        'hero' => [
            'heading' => ['kind' => 'text', 'max' => 120, 'required' => true],
            'subheading' => ['kind' => 'textarea', 'max' => 300],
            'image' => ['kind' => 'image'],
            'ctaLabel' => ['kind' => 'text', 'max' => 40],
        ],
        'text' => [
            'heading' => ['kind' => 'text', 'max' => 120],
            'body' => ['kind' => 'textarea', 'max' => 3000, 'required' => true],
        ],
        'features' => [
            'heading' => ['kind' => 'text', 'max' => 120],
            'items' => ['kind' => 'items', 'maxItems' => 8, 'fields' => [
                'title' => ['kind' => 'text', 'max' => 80, 'required' => true],
                'body' => ['kind' => 'textarea', 'max' => 400],
            ]],
        ],
        'steps' => [
            'heading' => ['kind' => 'text', 'max' => 120],
            'items' => ['kind' => 'items', 'maxItems' => 8, 'fields' => [
                'title' => ['kind' => 'text', 'max' => 80, 'required' => true],
                'body' => ['kind' => 'textarea', 'max' => 400],
            ]],
        ],
        'faq' => [
            'heading' => ['kind' => 'text', 'max' => 120],
            'items' => ['kind' => 'items', 'maxItems' => 12, 'fields' => [
                'question' => ['kind' => 'text', 'max' => 200, 'required' => true],
                'answer' => ['kind' => 'textarea', 'max' => 1000, 'required' => true],
            ]],
        ],
        'testimonials' => [
            'heading' => ['kind' => 'text', 'max' => 120],
            'items' => ['kind' => 'items', 'maxItems' => 8, 'fields' => [
                'quote' => ['kind' => 'textarea', 'max' => 500, 'required' => true],
                'author' => ['kind' => 'text', 'max' => 80, 'required' => true],
                'role' => ['kind' => 'text', 'max' => 80],
            ]],
        ],
        'profile' => [
            'heading' => ['kind' => 'text', 'max' => 120, 'required' => true],
            'body' => ['kind' => 'textarea', 'max' => 3000],
            'image' => ['kind' => 'image'],
            'credentials' => ['kind' => 'items', 'maxItems' => 8, 'fields' => [
                'title' => ['kind' => 'text', 'max' => 120, 'required' => true],
            ]],
        ],
        'event' => [
            'heading' => ['kind' => 'text', 'max' => 120],
            'date' => ['kind' => 'date'],
            'time' => ['kind' => 'text', 'max' => 40],
            'place' => ['kind' => 'text', 'max' => 160],
            'seats' => ['kind' => 'text', 'max' => 60],
        ],
        'form' => [
            'heading' => ['kind' => 'text', 'max' => 120],
            'body' => ['kind' => 'textarea', 'max' => 500],
            'submitLabel' => ['kind' => 'text', 'max' => 40, 'required' => true],
            'successMessage' => ['kind' => 'textarea', 'max' => 300, 'required' => true],
            // Lead magnet: the link the visitor gets by email once they send the form.
            'resourceUrl' => ['kind' => 'url', 'max' => 500],
        ],
    ];

    /** Accent colours a page can take: [name => [accent, accent text, soft background]], all readable (≥ 4.5:1). */
    public const ACCENTS = [
        'navy' => ['#1e3a5f', '#ffffff', '#e6edf5'],
        'green' => ['#1f6b3a', '#ffffff', '#e2f3e7'],
        'teal' => ['#0e6a6a', '#ffffff', '#dff1f0'],
        'plum' => ['#6b3fa0', '#ffffff', '#f1eaf8'],
        'rust' => ['#9a3b12', '#ffffff', '#fbeae2'],
        'graphite' => ['#2f3640', '#ffffff', '#eceef2'],
    ];

    /**
     * The sections of a template, in their default order, with default content.
     *
     * @return list<array{id: string, type: string, enabled: bool, fields: array<string, mixed>}>
     */
    public static function sections(PageTemplate $template): array
    {
        $form = static fn (string $heading, string $body, string $submit, string $success, string $resource = '') => self::section('formulario', 'form', [
            'heading' => $heading, 'body' => $body, 'submitLabel' => $submit, 'successMessage' => $success, 'resourceUrl' => $resource,
        ]);
        $faq = self::section('preguntas', 'faq', ['heading' => 'Preguntas frecuentes', 'items' => [
            ['question' => '¿Cuánto dura una sesión?', 'answer' => 'Cada sesión dura alrededor de una hora, por videollamada o en persona.'],
            ['question' => '¿Qué necesito tener listo?', 'answer' => 'Tus ingresos, tus gastos del mes y tus deudas, aunque sea aproximado. Lo organizamos juntos.'],
        ]]);
        $testimonials = self::section('testimonios', 'testimonials', ['heading' => 'Lo que dicen quienes ya lo hicieron', 'items' => [
            ['quote' => 'En tres meses salí de mis deudas de tarjeta y ahora ahorro cada mes.', 'author' => 'María P.', 'role' => 'Docente'],
            ['quote' => 'Por fin entiendo en qué se va mi plata y tengo un plan para mi pensión.', 'author' => 'Jorge R.', 'role' => 'Ingeniero'],
        ]]);

        return match ($template) {
            PageTemplate::FreeDiagnostic => [
                self::section('portada', 'hero', ['heading' => 'Ordena tus finanzas con un diagnóstico gratuito', 'subheading' => 'En una sesión de 45 minutos revisamos tus ingresos, gastos y deudas, y te llevas un primer plan de acción.', 'image' => null, 'ctaLabel' => 'Quiero mi diagnóstico']),
                self::section('problema', 'text', ['heading' => '¿Te suena familiar?', 'body' => "Llega fin de mes y no sabes en qué se fue el sueldo. Las deudas crecen aunque pagues cada mes. Quieres ahorrar, pero nunca queda nada.\n\nNo es falta de voluntad: es falta de un plan."]),
                self::section('como-funciona', 'steps', ['heading' => 'Cómo funciona', 'items' => [
                    ['title' => 'Déjanos tus datos', 'body' => 'Completa el formulario y te contactamos en menos de un día hábil.'],
                    ['title' => 'Conversamos 45 minutos', 'body' => 'Revisamos juntos tu situación, sin juicios y con total confidencialidad.'],
                    ['title' => 'Te llevas un plan', 'body' => 'Sales con tres acciones concretas para empezar esta misma semana.'],
                ]]),
                $faq,
                $form('Agenda tu diagnóstico gratuito', 'Déjanos tus datos y te escribimos para acordar la hora.', 'Quiero mi diagnóstico', '¡Gracias! Te escribiremos muy pronto para agendar tu diagnóstico.'),
            ],
            PageTemplate::PlanOffer => [
                self::section('portada', 'hero', ['heading' => 'Plan de finanzas personales en 2 sesiones', 'subheading' => 'Un acompañamiento corto y práctico para pasar del desorden a un plan que puedas cumplir.', 'image' => null, 'ctaLabel' => 'Quiero empezar']),
                self::section('beneficios', 'features', ['heading' => 'Lo que logras', 'items' => [
                    ['title' => 'Claridad', 'body' => 'Sabes exactamente cuánto entra, cuánto sale y a dónde va.'],
                    ['title' => 'Un plan para tus deudas', 'body' => 'Un orden de pago que reduce intereses y te da un fin a la vista.'],
                    ['title' => 'Ahorro automático', 'body' => 'Una meta y un monto mensual que no dependen de la fuerza de voluntad.'],
                ]]),
                self::section('incluye', 'features', ['heading' => 'Qué incluye', 'items' => [
                    ['title' => '2 sesiones de 60 minutos', 'body' => 'Por videollamada, en los horarios que elijas.'],
                    ['title' => 'Plantilla de presupuesto', 'body' => 'Tu presupuesto armado y listo para seguir.'],
                    ['title' => 'Seguimiento por correo', 'body' => 'Resolvemos tus dudas entre una sesión y otra.'],
                ]]),
                $testimonials,
                $faq,
                $form('¿Hablamos?', 'Déjanos tus datos y te contamos cómo empezar.', 'Quiero más información', '¡Gracias! Te escribiremos muy pronto con los detalles del plan.'),
            ],
            PageTemplate::ConsultantProfile => [
                self::section('portada', 'hero', ['heading' => 'Asesoría financiera personal, clara y sin letra pequeña', 'subheading' => 'Te acompaño a tomar decisiones sobre tu dinero con información, no con miedo.', 'image' => null, 'ctaLabel' => 'Hablemos']),
                self::section('sobre-mi', 'profile', ['heading' => 'Sobre mí', 'body' => "Soy asesor financiero con más de diez años acompañando a personas y familias a organizar sus finanzas, salir de deudas y planear su futuro.\n\nMi trabajo es explicarte tus opciones con palabras simples y ayudarte a elegir la que te conviene.", 'image' => null, 'credentials' => [
                    ['title' => 'Profesional en Finanzas'],
                    ['title' => 'Certificación en Planeación Financiera'],
                ]]),
                self::section('servicios', 'features', ['heading' => 'Cómo te puedo ayudar', 'items' => [
                    ['title' => 'Salir de deudas', 'body' => 'Un plan realista para pagar y no volver a endeudarte.'],
                    ['title' => 'Ahorro e inversión', 'body' => 'Metas claras y en qué invertir según tu perfil.'],
                    ['title' => 'Pensión y retiro', 'body' => 'Qué te conviene y cuánto necesitas ahorrar desde hoy.'],
                ]]),
                $testimonials,
                $form('Escríbeme', 'Cuéntame qué te preocupa y te respondo personalmente.', 'Enviar', '¡Gracias por escribir! Te responderé muy pronto.'),
            ],
            PageTemplate::Event => [
                self::section('portada', 'hero', ['heading' => 'Taller: finanzas personales en un sábado', 'subheading' => 'Tres horas para aprender a hacer tu presupuesto, ordenar tus deudas y empezar a ahorrar.', 'image' => null, 'ctaLabel' => 'Reservar mi cupo']),
                self::section('detalles', 'event', ['heading' => 'Cuándo y dónde', 'date' => null, 'time' => '9:00 a. m. – 12:00 m.', 'place' => 'En línea, por videollamada', 'seats' => 'Cupos limitados']),
                self::section('agenda', 'steps', ['heading' => 'Agenda', 'items' => [
                    ['title' => 'Tu presupuesto en una hoja', 'body' => 'Cómo registrar ingresos y gastos sin complicarte.'],
                    ['title' => 'Deudas: por dónde empezar', 'body' => 'Bola de nieve o avalancha: cuál te conviene.'],
                    ['title' => 'Ahorro que sí se cumple', 'body' => 'Metas, fondos de emergencia y automatización.'],
                ]]),
                self::section('quien-dicta', 'profile', ['heading' => 'Quién dicta el taller', 'body' => 'Asesor financiero con más de diez años de experiencia en finanzas personales.', 'image' => null, 'credentials' => []]),
                $faq,
                $form('Reserva tu cupo', 'Déjanos tus datos y te enviamos el enlace de conexión.', 'Reservar mi cupo', '¡Listo! Te enviaremos el enlace y los detalles antes del taller.'),
            ],
            PageTemplate::LeadMagnet => [
                self::section('portada', 'hero', ['heading' => 'Descarga gratis la plantilla de presupuesto mensual', 'subheading' => 'La misma que uso con mis clientes: en 15 minutos sabrás a dónde se va tu dinero.', 'image' => null, 'ctaLabel' => 'Quiero la plantilla']),
                self::section('que-incluye', 'features', ['heading' => 'Qué recibes', 'items' => [
                    ['title' => 'Plantilla lista para usar', 'body' => 'En Excel y Google Sheets.'],
                    ['title' => 'Guía paso a paso', 'body' => 'Cómo llenarla aunque nunca hayas hecho un presupuesto.'],
                    ['title' => 'Ejemplo real', 'body' => 'Un presupuesto de ejemplo para que veas cómo queda.'],
                ]]),
                $form('Recíbela en tu correo', 'Te enviamos el enlace de descarga al instante.', 'Enviarme la plantilla', '¡Listo! Revisa tu correo: te enviamos el enlace de descarga.'),
            ],
        };
    }

    /**
     * A new page's content: the template's sections and empty form, SEO from its title, the default accent.
     *
     * @return array<string, mixed>
     */
    public static function newContent(PageTemplate $template, string $title): array
    {
        return [
            'sections' => self::sections($template),
            'form' => ['fields' => []],
            'seo' => ['title' => $title, 'description' => '', 'imageId' => null, 'index' => true],
            'settings' => ['defaultCategoryId' => null, 'accent' => 'navy'],
        ];
    }

    /**
     * @param array<string, mixed> $fields
     *
     * @return array{id: string, type: string, enabled: bool, fields: array<string, mixed>}
     */
    private static function section(string $id, string $type, array $fields): array
    {
        return ['id' => $id, 'type' => $type, 'enabled' => true, 'fields' => $fields];
    }
}
