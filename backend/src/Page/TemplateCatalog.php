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
 * Field kinds: text (one line), textarea, image (a MediaAsset id), date (Y-m-d), url, items (a repeatable group),
 * plan (a free Plan id: what a booking section books), plans (1 to 3 paid Plan ids: what a payment section sells).
 *
 * Every section works in any order the consultant puts it in: the page's buttons (hero, cta band, sticky bar) all
 * lead to its first form, booking or payment section, wherever that is (PageRenderer).
 *
 * A template may gain sections over time: a page made before gets them appended, switched off (ContentValidator).
 */
final class TemplateCatalog
{
    /**
     * @var array<string, array<string, array{kind: string, max?: int, required?: bool, maxItems?: int, fields?: array<string, array{kind: string, max?: int, required?: bool}>}>>
     */
    public const SECTION_TYPES = [
        'hero' => [
            // A short tag above the title ("Asesoría financiera personal").
            'eyebrow' => ['kind' => 'text', 'max' => 60],
            'heading' => ['kind' => 'text', 'max' => 120, 'required' => true],
            'subheading' => ['kind' => 'textarea', 'max' => 300],
            'image' => ['kind' => 'image'],
            'ctaLabel' => ['kind' => 'text', 'max' => 40],
            // What takes away the fear of asking, under the button ("Sin costo", "45 minutos").
            'trustPoints' => ['kind' => 'items', 'maxItems' => 4, 'fields' => [
                'text' => ['kind' => 'text', 'max' => 40, 'required' => true],
            ]],
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
        'booking' => [
            'heading' => ['kind' => 'text', 'max' => 120],
            'body' => ['kind' => 'textarea', 'max' => 500],
            'planId' => ['kind' => 'plan', 'required' => true],
            'submitLabel' => ['kind' => 'text', 'max' => 40, 'required' => true],
            'successMessage' => ['kind' => 'textarea', 'max' => 300, 'required' => true],
            // Beside the card: what the visitor gets or what happens next.
            'highlights' => ['kind' => 'items', 'maxItems' => 4, 'fields' => [
                'text' => ['kind' => 'text', 'max' => 100, 'required' => true],
            ]],
        ],
        'payment' => [
            'heading' => ['kind' => 'text', 'max' => 120],
            'body' => ['kind' => 'textarea', 'max' => 500],
            'planIds' => ['kind' => 'plans', 'required' => true, 'maxItems' => 3],
            'submitLabel' => ['kind' => 'text', 'max' => 40, 'required' => true],
            'note' => ['kind' => 'textarea', 'max' => 300],
            // Beside the card: what the visitor gets or what happens next.
            'highlights' => ['kind' => 'items', 'maxItems' => 4, 'fields' => [
                'text' => ['kind' => 'text', 'max' => 100, 'required' => true],
            ]],
        ],
        // A band in the accent colour that sends the visitor to the page's form, booking or payment.
        'cta' => [
            'heading' => ['kind' => 'text', 'max' => 120, 'required' => true],
            'body' => ['kind' => 'textarea', 'max' => 300],
            'buttonLabel' => ['kind' => 'text', 'max' => 40, 'required' => true],
        ],
        'form' => [
            'heading' => ['kind' => 'text', 'max' => 120],
            'body' => ['kind' => 'textarea', 'max' => 500],
            'submitLabel' => ['kind' => 'text', 'max' => 40, 'required' => true],
            'successMessage' => ['kind' => 'textarea', 'max' => 300, 'required' => true],
            // Lead magnet: the link the visitor gets by email once they send the form.
            'resourceUrl' => ['kind' => 'url', 'max' => 500],
            // Beside the card: what the visitor gets or what happens next.
            'highlights' => ['kind' => 'items', 'maxItems' => 4, 'fields' => [
                'text' => ['kind' => 'text', 'max' => 100, 'required' => true],
            ]],
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
     * A payment section, off until the consultant picks the paid plans it sells (Planes) and turns it on.
     *
     * @return array{id: string, type: string, enabled: bool, fields: array<string, mixed>}
     */
    private static function payment(string $id, string $heading, string $body): array
    {
        return ['id' => $id, 'type' => 'payment', 'enabled' => false, 'fields' => [
            'heading' => $heading, 'body' => $body, 'planIds' => [], 'submitLabel' => 'Pagar con Wompi',
            'note' => 'Pago seguro con Wompi: tarjeta, PSE o Nequi. Te enviamos el comprobante a tu correo.',
            'highlights' => self::points('Pago seguro con tarjeta, PSE o Nequi', 'Recibes el comprobante en tu correo', 'Te escribimos para agendar tus sesiones'),
        ]];
    }

    /**
     * The sections of a template, in their default order, with default content.
     *
     * @return list<array{id: string, type: string, enabled: bool, fields: array<string, mixed>}>
     */
    public static function sections(PageTemplate $template): array
    {
        $form = static fn (string $heading, string $body, string $submit, string $success, array $highlights, string $resource = '') => self::section('formulario', 'form', [
            'heading' => $heading, 'body' => $body, 'submitLabel' => $submit, 'successMessage' => $success, 'resourceUrl' => $resource, 'highlights' => $highlights,
        ]);
        $hero = static fn (string $eyebrow, string $heading, string $subheading, string $cta, array $trust) => self::section('portada', 'hero', [
            'eyebrow' => $eyebrow, 'heading' => $heading, 'subheading' => $subheading, 'image' => null, 'ctaLabel' => $cta, 'trustPoints' => $trust,
        ]);
        $cta = static fn (string $heading, string $body, string $button, bool $enabled = true) => ['id' => 'llamado', 'type' => 'cta', 'enabled' => $enabled, 'fields' => [
            'heading' => $heading, 'body' => $body, 'buttonLabel' => $button,
        ]];
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
                $hero('Diagnóstico financiero gratuito', 'Ordena tus finanzas con un diagnóstico gratuito', 'En una sesión de 45 minutos revisamos tus ingresos, gastos y deudas, y te llevas un primer plan de acción.', 'Quiero mi diagnóstico', self::points('Sin costo', '45 minutos', '100 % confidencial')),
                self::section('problema', 'text', ['heading' => '¿Te suena familiar?', 'body' => "Llega fin de mes y no sabes en qué se fue el sueldo. Las deudas crecen aunque pagues cada mes. Quieres ahorrar, pero nunca queda nada.\n\nNo es falta de voluntad: es falta de un plan."]),
                self::section('como-funciona', 'steps', ['heading' => 'Cómo funciona', 'items' => [
                    ['title' => 'Déjanos tus datos', 'body' => 'Completa el formulario y te contactamos en menos de un día hábil.'],
                    ['title' => 'Conversamos 45 minutos', 'body' => 'Revisamos juntos tu situación, sin juicios y con total confidencialidad.'],
                    ['title' => 'Te llevas un plan', 'body' => 'Sales con tres acciones concretas para empezar esta misma semana.'],
                ]]),
                // Off until the consultant picks the free plan it books (Planes) and turns it on.
                ['id' => 'reserva', 'type' => 'booking', 'enabled' => false, 'fields' => ['heading' => 'Elige el día y la hora', 'body' => 'Tu diagnóstico dura 45 minutos, por videollamada. Te enviamos el enlace al confirmar.', 'planId' => null, 'submitLabel' => 'Reservar mi diagnóstico', 'successMessage' => '¡Listo! Tu sesión quedó reservada. Te enviamos la confirmación a tu correo.', 'highlights' => self::points('Confirmación inmediata en tu correo', 'Recordatorio antes de la sesión', 'Puedes cambiar la hora si lo necesitas')]],
                $cta('Da el primer paso hoy', 'La primera conversación es gratuita y sin compromiso.', 'Quiero mi diagnóstico'),
                $faq,
                $form('Agenda tu diagnóstico gratuito', 'Déjanos tus datos y te escribimos para acordar la hora.', 'Quiero mi diagnóstico', '¡Gracias! Te escribiremos muy pronto para agendar tu diagnóstico.', self::points('Te respondemos en menos de un día hábil', 'Sin costo y sin compromiso', 'Tus datos se quedan entre nosotros')),
            ],
            PageTemplate::PlanOffer => [
                $hero('Acompañamiento financiero', 'Plan de finanzas personales en 2 sesiones', 'Un acompañamiento corto y práctico para pasar del desorden a un plan que puedas cumplir.', 'Quiero empezar', self::points('2 sesiones de 60 minutos', 'Por videollamada', 'Plan a tu medida')),
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
                self::payment('precios', 'Empieza hoy', 'Elige tu plan, págalo en línea y te escribimos para agendar tus sesiones.'),
                $testimonials,
                $cta('Tu plan puede empezar esta semana', 'Cuéntanos tu situación y te decimos cómo empezar.', 'Quiero empezar'),
                $faq,
                $form('¿Hablamos?', 'Déjanos tus datos y te contamos cómo empezar.', 'Quiero más información', '¡Gracias! Te escribiremos muy pronto con los detalles del plan.', self::points('Te respondemos en menos de un día hábil', 'Sin compromiso', 'Tus datos se quedan entre nosotros')),
            ],
            PageTemplate::ConsultantProfile => [
                $hero('Asesoría financiera personal', 'Asesoría financiera personal, clara y sin letra pequeña', 'Te acompaño a tomar decisiones sobre tu dinero con información, no con miedo.', 'Hablemos', self::points('Más de 10 años de experiencia', 'Atención personal', 'Sin letra pequeña')),
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
                $cta('¿Conversamos sobre tu dinero?', 'La primera conversación es sin compromiso.', 'Hablemos', false),
                $form('Escríbeme', 'Cuéntame qué te preocupa y te respondo personalmente.', 'Enviar', '¡Gracias por escribir! Te responderé muy pronto.', self::points('Te respondo personalmente', 'En menos de un día hábil', 'Tus datos se quedan entre nosotros')),
            ],
            PageTemplate::Event => [
                $hero('Taller en línea', 'Taller: finanzas personales en un sábado', 'Tres horas para aprender a hacer tu presupuesto, ordenar tus deudas y empezar a ahorrar.', 'Reservar mi cupo', self::points('3 horas', 'En línea', 'Cupos limitados')),
                self::section('detalles', 'event', ['heading' => 'Cuándo y dónde', 'date' => null, 'time' => '9:00 a. m. – 12:00 m.', 'place' => 'En línea, por videollamada', 'seats' => 'Cupos limitados']),
                self::section('agenda', 'steps', ['heading' => 'Agenda', 'items' => [
                    ['title' => 'Tu presupuesto en una hoja', 'body' => 'Cómo registrar ingresos y gastos sin complicarte.'],
                    ['title' => 'Deudas: por dónde empezar', 'body' => 'Bola de nieve o avalancha: cuál te conviene.'],
                    ['title' => 'Ahorro que sí se cumple', 'body' => 'Metas, fondos de emergencia y automatización.'],
                ]]),
                self::payment('pago', 'Paga tu cupo', 'Asegura tu lugar pagando en línea.'),
                self::section('quien-dicta', 'profile', ['heading' => 'Quién dicta el taller', 'body' => 'Asesor financiero con más de diez años de experiencia en finanzas personales.', 'image' => null, 'credentials' => []]),
                $cta('Los cupos son limitados', 'Reserva el tuyo hoy y recibe el enlace en tu correo.', 'Reservar mi cupo'),
                $faq,
                $form('Reserva tu cupo', 'Déjanos tus datos y te enviamos el enlace de conexión.', 'Reservar mi cupo', '¡Listo! Te enviaremos el enlace y los detalles antes del taller.', self::points('Recibes el enlace en tu correo', 'Recordatorio antes del taller', 'Material de apoyo incluido')),
            ],
            PageTemplate::LeadMagnet => [
                $hero('Recurso gratuito', 'Descarga gratis la plantilla de presupuesto mensual', 'La misma que uso con mis clientes: en 15 minutos sabrás a dónde se va tu dinero.', 'Quiero la plantilla', self::points('Gratis', 'Excel y Google Sheets', 'Lista en 15 minutos')),
                self::section('que-incluye', 'features', ['heading' => 'Qué recibes', 'items' => [
                    ['title' => 'Plantilla lista para usar', 'body' => 'En Excel y Google Sheets.'],
                    ['title' => 'Guía paso a paso', 'body' => 'Cómo llenarla aunque nunca hayas hecho un presupuesto.'],
                    ['title' => 'Ejemplo real', 'body' => 'Un presupuesto de ejemplo para que veas cómo queda.'],
                ]]),
                $cta('Empieza a ordenar tu dinero hoy', 'Te la enviamos gratis a tu correo.', 'Quiero la plantilla', false),
                $form('Recíbela en tu correo', 'Te enviamos el enlace de descarga al instante.', 'Enviarme la plantilla', '¡Listo! Revisa tu correo: te enviamos el enlace de descarga.', self::points('Llega a tu correo al instante', 'Sin costo', 'Puedes darte de baja cuando quieras')),
            ],
        };
    }

    /**
     * An "items" field of one-line texts (trust points, highlights).
     *
     * @return list<array{text: string}>
     */
    private static function points(string ...$texts): array
    {
        return array_map(static fn (string $text) => ['text' => $text], array_values($texts));
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
            'settings' => ['defaultCategoryId' => null, 'accent' => 'navy', 'flowId' => null],
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
