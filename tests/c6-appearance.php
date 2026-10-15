<?php

declare(strict_types=1);

use NicheCleverInteractive\Core\Configuration\CoreSettings;
use NicheCleverInteractive\Core\Configuration\PanelAppearance;
use NicheCleverInteractive\Core\Frontend\InteractivePanel;

require_once __DIR__ . '/bootstrap.php';
require_once dirname(__DIR__) . '/src/autoload.php';

$ncliPanelOptions = [];
function get_option(string $name, $default = false)
{
    global $ncliPanelOptions;
    return $ncliPanelOptions[$name] ?? $default;
}

$failures = [];
$assert = static function (bool $condition, string $message) use (&$failures): void {
    if (!$condition) {
        $failures[] = $message;
    }
};

// Colour validation and contrast.
foreach (['#000000', '#FFFFFF', '#a1B2c3'] as $good) {
    $assert(PanelAppearance::isHexColour($good), 'A six-digit hex colour must be accepted: ' . $good);
}
foreach (['', '#fff', '#12345', '#1234567', 'red', 'rgb(0,0,0)', 'url(x)', '#gggggg', 'expression(1)', "#000000\n"] as $bad) {
    $assert(!PanelAppearance::isHexColour($bad), 'A non-hex colour must be rejected: ' . json_encode($bad));
}
$assert(abs(PanelAppearance::contrastRatio('#000000', '#ffffff') - 21.0) < 0.01, 'Black on white must have the WCAG maximum contrast of 21:1.');
$assert(PanelAppearance::contrastRatio('#777777', '#777777') === 1.0, 'Identical colours must have 1:1 contrast.');
$assert(PanelAppearance::effectiveAccent('classic', '') === '', 'No accent means the style colour.');
$assert(PanelAppearance::effectiveAccent('classic', '#C2410C') === '#c2410c', 'A readable accent must be kept, in lower case.');
foreach (['classic', 'minimal', 'dark'] as $everyStyle) {
    foreach (['#ff8040', '#f5f5f5', '#fefefe', '#111111', '#7dd3fc', '#C2410C'] as $everyColour) {
        $assert(PanelAppearance::effectiveAccent($everyStyle, $everyColour) === strtolower($everyColour), 'The owner accent must apply in every style and be kept whatever its brightness: ' . $everyStyle . ' ' . $everyColour);
    }
}
// Readability is protected without discarding the owner's choice.
$assert(PanelAppearance::inlineStyle('classic', 'standard', '#ff8040') === '--ncli-accent:#ff8040;--ncli-on-accent:#111111;--ncli-focus:#111111;--ncli-edge:#111111', 'A pale accent on Classic must still apply, with a button edge and a safe focus colour.');
$assert(PanelAppearance::inlineStyle('minimal', 'standard', '#ff8040') === '--ncli-accent:#ff8040;--ncli-on-accent:#111111;--ncli-focus:#2563eb;--ncli-edge:#1f2937', 'A pale accent on Minimal must still apply, with a button edge and the style focus colour.');
$assert(PanelAppearance::inlineStyle('dark', 'standard', '#ff8040') === '--ncli-accent:#ff8040;--ncli-on-accent:#111111', 'An accent that already contrasts with the Dark panel needs no extra protection.');
$assert(PanelAppearance::inlineStyle('dark', 'standard', '#111111') === '--ncli-accent:#111111;--ncli-on-accent:#ffffff;--ncli-focus:#7dd3fc;--ncli-edge:#e8eaed', 'A near-black accent on Dark must still apply, with an edge so the button stays visible.');
foreach (['classic', 'minimal', 'dark'] as $everyStyle) {
    foreach (['narrow', 'standard', 'wide'] as $everyWidth) {
        $declared = PanelAppearance::inlineStyle($everyStyle, $everyWidth, '#ff8040');
        $assert(str_contains($declared, '--ncli-accent:#ff8040') && ($everyWidth === 'standard' || str_contains($declared, '--ncli-max:')), 'The accent must apply in every style at every width: ' . $everyStyle . ' ' . $everyWidth);
    }
}
$assert(PanelAppearance::onAccent('#ffffff') === '#111111' && PanelAppearance::onAccent('#111111') === '#ffffff', 'Button text must contrast with the accent.');
$assert(PanelAppearance::onAccent('#c2410c') === '#ffffff', 'A strong orange accent should take white text.');
$assert(PanelAppearance::defaultAccent('minimal') === '#2563eb' && PanelAppearance::defaultAccent('unknown') === '#111111', 'Style accents must match the public stylesheet.');

// Public stylesheet accents agree with the PHP defaults used for the contrast check.
$css = (string) file_get_contents(dirname(__DIR__) . '/assets/css/public.css');
$assert(str_contains($css, '--ncli-accent: #111;') && str_contains($css, '--ncli-accent: #2563eb;') && str_contains($css, '--ncli-accent: #7dd3fc;'), 'The style accents in CSS must match PanelAppearance.');
$assert(str_contains($css, '--ncli-bg: #0f1115'), 'The Dark background in CSS must match the contrast check.');

// Inline style output is built only from fixed values.
$assert(PanelAppearance::inlineStyle('classic', 'standard', '') === '', 'Default appearance must add no inline style.');
$assert(PanelAppearance::inlineStyle('classic', 'narrow', '') === '--ncli-max:36rem', 'Narrow width must set the maximum width.');
$assert(PanelAppearance::inlineStyle('classic', 'wide', '') === '--ncli-max:64rem', 'Wide width must set the maximum width.');
$assert(PanelAppearance::inlineStyle('classic', 'bogus', '') === '', 'An unknown width must add nothing.');
$assert(PanelAppearance::inlineStyle('dark', 'standard', '#7dd3fc') === '--ncli-accent:#7dd3fc;--ncli-on-accent:#111111', 'An accent must set both the accent and its text colour.');
$assert(str_contains($css, 'var(--ncli-focus, var(--ncli-accent))') && str_contains($css, 'inset 0 0 0 1px var(--ncli-edge, transparent)'), 'The stylesheet must use the focus colour and button edge when they are supplied.');
$assert(PanelAppearance::inlineStyle('classic', 'standard', 'red;background:url(x)') === '', 'A non-hex accent must never reach the style attribute.');

// Public rendering.
$panel = new InteractivePanel();
$default = $panel->renderInteractive();
$assert(!str_contains($default, ' style="'), 'The default panel must carry no inline style.');
$assert(!str_contains($default, 'ncli-panel__credit') && !str_contains($default, 'Powered by'), 'The credit line must be absent by default.');
$assert(str_contains($default, 'This interactive service is not available yet.'), 'The default unavailable message must be shown while unready.');

$ncliPanelOptions[CoreSettings::OPTION_NAME] = CoreSettings::fromArray([
    'appearance' => ['panel_style' => 'dark', 'panel_width' => 'narrow', 'accent_color' => '#C2410C', 'show_credit' => true],
    'presentation' => ['unavailable_message' => 'Back <b>soon</b> & "quoted"'],
])->toArray();
$custom = $panel->renderInteractive();
$assert(str_contains($custom, 'style="--ncli-max:36rem;--ncli-accent:#c2410c;--ncli-on-accent:#ffffff"'), 'Width and accent must be applied through exactly the expected custom properties.');
$assert(!str_contains($custom, '<b>soon</b>') && str_contains($custom, 'Back &lt;b&gt;soon&lt;/b&gt;'), 'The unavailable message must be escaped.');
$assert(str_contains($custom, '<p class="ncli-panel__credit"><a href="https://nicheclever.co.uk/" target="_blank" rel="noopener">Powered by NC Interactive</a></p>'), 'The opt-in credit must render a safe external link.');
$assert(strpos($custom, 'ncli-panel__credit') > strpos($custom, 'ncli-panel__footer'), 'The credit must sit below the panel footer.');

$ncliPanelOptions[CoreSettings::OPTION_NAME] = CoreSettings::fromArray([
    'appearance' => ['panel_style' => 'dark', 'accent_color' => '#111111'],
])->toArray();
$lowContrast = $panel->renderInteractive();
$assert(str_contains($lowContrast, '--ncli-accent:#111111') && str_contains($lowContrast, '--ncli-edge:#e8eaed'), 'A low-contrast saved accent must still be applied, with an edge for visibility.');

$ncliPanelOptions[CoreSettings::OPTION_NAME] = CoreSettings::fromArray(['presentation' => ['unavailable_message' => '']])->toArray();
$assert(str_contains($panel->renderInteractive(), CoreSettings::DEFAULT_UNAVAILABLE_MESSAGE), 'A blank unavailable message must fall back to the default wording.');

// Settings validation of the new appearance fields.
foreach ([['panel_width' => 'huge'], ['accent_color' => 'red'], ['accent_color' => '#fff'], ['show_credit' => 'yes']] as $bad) {
    $threw = false;
    try {
        CoreSettings::fromArray(['appearance' => $bad]);
    } catch (InvalidArgumentException $exception) {
        $threw = true;
    }
    $assert($threw, 'Malformed appearance input must be rejected: ' . json_encode($bad));
}
$old = CoreSettings::fromArray(['appearance' => ['panel_style' => 'classic'], 'presentation' => ['title' => 'Kept']]);
$assert($old->panelWidth() === 'standard' && $old->accentColor() === '' && $old->showCredit() === false && $old->presentation()['title'] === 'Kept', 'Settings saved by an earlier candidate must gain the new defaults.');

// Public script boundary for the conversation identifier.
$js = (string) file_get_contents(dirname(__DIR__) . '/assets/js/public.js');
$assert(str_contains($js, 'conversation_id') && str_contains($js, "removeAttribute('data-ncli-conversation')"), 'The client must send an in-memory conversation identifier and discard it on reset.');
$assert(!preg_match('/localStorage|sessionStorage|document\.cookie/', $js), 'The conversation identifier must never be persisted in the browser.');

if ($failures !== []) {
    fwrite(STDERR, "FAIL\n- " . implode("\n- ", $failures) . "\n");
    exit(1);
}

echo "PASS: C6 appearance and public-panel checks verified\n";
