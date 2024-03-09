<?php

namespace WpPasskeys\Admin;

class AdminUtilities
{
    /**
     * @param string $lastUsedOS
     *
     * @return bool
     */
    public static function isDeviceMobile(string $lastUsedOS): bool
    {
        return in_array($lastUsedOS, ['iOS', 'Android']);
    }

    /**
     * General method to render buttons
     */
    public static function renderButton(string $label, string $value, string $extraClass): string
    {
        return sprintf(
            '<button type="button" class="button %s" value="%s">%s</button>',
            $extraClass,
            $value,
            $label
        );
    }
}
