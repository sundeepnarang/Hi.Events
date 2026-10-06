<?php

namespace HiEvents\DomainObjects;

class EventSettingDomainObject extends Generated\EventSettingDomainObjectAbstract
{
    /**
     * @todo This should not be here.
     */
    public function getGetEmailFooterHtml(): string
    {
        if ($this->getEmailFooterMessage() === null) {
            return '';
        }

        return <<<HTML
<div style="color: #888; margin-top: 30px; margin-bottom: 30px; font-size: .9em;">
    {$this->getEmailFooterMessage()}
</div>
HTML;
    }

    public function getTrackingPixels(): ?array
    {
        if (is_string($this->tracking_pixels)) {
            return json_decode($this->tracking_pixels, true);
        }

        return $this->tracking_pixels;
    }

    public function setTrackingPixels(array|string|null $trackingPixels): self
    {
        $this->tracking_pixels = is_string($trackingPixels)
            ? json_decode($trackingPixels, true)
            : $trackingPixels;

        return $this;
    }
}
