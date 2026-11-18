<?php

namespace App\Http\Resources\Setting;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class HomeGeneralSettingResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $legacyType = ($this->value['backgroundType'] ?? '') === 'image'
            ? 'image'
            : (($this->value['backgroundType'] ?? '') === 'color' ? 'gradient' : 'none');
        $legacyColor = $this->value['backgroundColor'] ?? '#ffffff';
        $app = array_merge([
            'background_type' => $legacyType,
            'gradient_start' => $legacyColor,
            'gradient_end' => $legacyColor,
            'gradient_angle' => 90,
            'font_color' => $this->value['fontColor'] ?? '#475569',
            'active_font_color' => '#111827',
        ], (array) data_get($this->value, 'homeAppearance.app', []));
        $desktop = array_merge($app, (array) data_get($this->value, 'homeAppearance.desktop', []));

        $url = static fn (?string $path): string => $path ? url('storage/'.$path) : '';
        $appBackgroundImage = $url($this->value['backgroundImage'] ?? '');
        $appTabletBackgroundImage = $url($this->value['appTabletBackgroundImage'] ?? '');
        $desktopConfiguredBackgroundImage = $url($this->value['desktopBackgroundImage'] ?? '');
        $desktopBackgroundImage = $desktopConfiguredBackgroundImage ?: $appBackgroundImage;
        $desktopTabletBackgroundImage = $url($this->value['desktopTabletBackgroundImage'] ?? '');
        $desktopMobileBackgroundImage = $url($this->value['desktopMobileBackgroundImage'] ?? '');
        $app = array_merge($app, [
            'background_image' => $appBackgroundImage,
            'mobile_background_image' => $appBackgroundImage,
            'tablet_background_image' => $appTabletBackgroundImage ?: $appBackgroundImage,
        ]);
        $desktop = array_merge($desktop, [
            'background_image' => $desktopBackgroundImage,
            'desktop_background_image' => $desktopBackgroundImage,
            'tablet_background_image' => $desktopTabletBackgroundImage ?: $desktopBackgroundImage,
            'mobile_background_image' => $desktopMobileBackgroundImage ?: $desktopTabletBackgroundImage ?: $desktopBackgroundImage,
            'app_background_image' => $desktopMobileBackgroundImage ?: $desktopTabletBackgroundImage ?: $desktopBackgroundImage,
        ]);

        return [
            'variable' => $this->variable,
            'value' => [
                'title' => $this->value['title'] ?? '',
                'searchLabels' => $this->value['searchLabels'] ?? [],
                'backgroundType' => $this->value['backgroundType'] ?? '',
                'backgroundColor' => $this->value['backgroundColor'] ?? '#ffffff',
                'backgroundImage' => $appBackgroundImage,
                'appTabletBackgroundImage' => $appTabletBackgroundImage,
                'icon' => ! empty($this->value['icon']) ? url('storage/'.$this->value['icon']) : '',
                'activeIcon' => ! empty($this->value['activeIcon']) ? url('storage/'.$this->value['activeIcon']) : '',
                'desktopIcon' => $url($this->value['desktopIcon'] ?? ($this->value['icon'] ?? '')),
                'desktopActiveIcon' => $url($this->value['desktopActiveIcon'] ?? ($this->value['activeIcon'] ?? '')),
                'desktopBackgroundImage' => $desktopConfiguredBackgroundImage,
                'desktopTabletBackgroundImage' => $desktopTabletBackgroundImage,
                'desktopMobileBackgroundImage' => $desktopMobileBackgroundImage,
                'fontColor' => $this->value['fontColor'] ?? '#000000',
                'homeAppearance' => ['app' => $app, 'desktop' => $desktop],
            ],
        ];
    }
}
