<?php

/** @copyright */

namespace App\Application\DataTransformer\Apps\Media\DataTransformers\Widget\Details;

use Ec\Widget\Domain\Model\HtmlWidget;
use Ec\Widget\Domain\Model\Widget;

/**
 * @author Laura Gómez Cabero <lgomez@ext.elconfidencial.com>
 */
class HtmlWidgetDataTransformer implements WidgetTypeDataTransformer
{
    protected Widget $widget;

    /**
     * @param Widget $widget
     *
     * @return HtmlWidgetDataTransformer
     */
    public function write(Widget $widget): self
    {
        $this->widget = $widget;

        return $this;
    }

    /**
     * @return array<string, mixed>
     */
    public function read(): array
    {
        if (!$this->widget instanceof HtmlWidget) {
            return [];
        }

        /** @var HtmlWidget $htmlWidget */
        $htmlWidget = $this->widget;

        return [
            'url' => $htmlWidget->url(),
            'aspectRatio' => $this->calculateAspectRatio($htmlWidget->params()),
            'name' => $htmlWidget->name(),
            'description' => $htmlWidget->description(),
            'body' => $htmlWidget->body(),
            'visible' => $htmlWidget->isVisible(),
            'home' => $htmlWidget->home(),
            'cache' => $htmlWidget->cache(),
        ];
    }

    public function canTransform(): string
    {
        return 'html';
    }

    /**
     * @param array<string, mixed> $params
     */
    private function calculateAspectRatio(array $params): ?float
    {
        $aspectRatio = null;

        if (!empty($params['aspect-ratio'])
            && \is_string($params['aspect-ratio'])
            && str_contains($params['aspect-ratio'], '/')) {
            $parts = explode('/', $params['aspect-ratio']);

            if (2 === \count($parts)
                && is_numeric(trim($parts[0]))
                && is_numeric(trim($parts[1]))) {
                $numerator = (float) trim($parts[0]);
                $denominator = (float) trim($parts[1]);

                if (0.0 !== $denominator) {
                    $aspectRatio = round($numerator / $denominator, 1);
                }
            }
        }

        return $aspectRatio;
    }
}
