<?php
/** Return a trusted inline SVG icon from the Learner icon whitelist. */
if (!function_exists('learner_icon')) {
    function learner_icon(string $name, int $size = 20): string
    {
        $size = max(12, min(64, $size));
        $paths = [
            'grid' => '<rect x="3" y="3" width="7" height="7" rx="1"/><rect x="14" y="3" width="7" height="7" rx="1"/><rect x="3" y="14" width="7" height="7" rx="1"/><rect x="14" y="14" width="7" height="7" rx="1"/>',
            'user' => '<path d="M20 21a8 8 0 0 0-16 0"/><circle cx="12" cy="7" r="4"/>',
            'compass' => '<circle cx="12" cy="12" r="9"/><path d="m16 8-2.5 5.5L8 16l2.5-5.5L16 8Z"/>',
            'calendar' => '<rect x="3" y="5" width="18" height="16" rx="2"/><path d="M16 3v4M8 3v4M3 10h18"/>',
            'qr' => '<rect x="3" y="3" width="6" height="6"/><rect x="15" y="3" width="6" height="6"/><rect x="3" y="15" width="6" height="6"/><path d="M15 15h2v2h-2zM19 15h2v6h-2M15 19h2v2h-2"/>',
            'clipboard' => '<rect x="5" y="4" width="14" height="17" rx="2"/><path d="M9 4V2h6v2M9 11l2 2 4-4"/>',
            'sparkles' => '<path d="m12 3 1.4 3.6L17 8l-3.6 1.4L12 13l-1.4-3.6L7 8l3.6-1.4L12 3ZM5 14l.8 2.2L8 17l-2.2.8L5 20l-.8-2.2L2 17l2.2-.8L5 14ZM19 13l.8 2.2L22 16l-2.2.8L19 19l-.8-2.2L16 16l2.2-.8L19 13Z"/>',
            'award' => '<circle cx="12" cy="8" r="6"/><path d="m8.5 13-1 9 4.5-3 4.5 3-1-9"/>',
            'chart' => '<path d="M4 20V10M10 20V4M16 20v-7M22 20H2"/>',
            'menu' => '<path d="M4 6h16M4 12h16M4 18h16"/>',
            'bell' => '<path d="M18 8a6 6 0 0 0-12 0c0 7-3 9-3 9h18s-3-2-3-9M14 21h-4"/>',
            'search' => '<circle cx="11" cy="11" r="7"/><path d="m20 20-4-4"/>',
            'arrow-left' => '<path d="m15 18-6-6 6-6M9 12h12"/>',
            'arrow-right' => '<path d="M5 12h14m-6-6 6 6-6 6"/>',
            'log-out' => '<path d="M10 17l5-5-5-5M15 12H3"/><path d="M11 4h7a2 2 0 0 1 2 2v12a2 2 0 0 1-2 2h-7"/>',
            'flame' => '<path d="M12 22c4 0 7-3 7-7 0-5-3-8-6-12 0 4-3 6-5 9-1-2-1-3-1-5-2 3-3 5-3 8 0 4 4 7 8 7Z"/>',
            'star' => '<path d="m12 3 2.8 5.7 6.2.9-4.5 4.4 1.1 6.2-5.6-3-5.6 3 1.1-6.2L3 9.6l6.2-.9L12 3Z"/>',
            'trophy' => '<path d="M8 4h8v5a4 4 0 0 1-8 0V4ZM12 13v5M8 21h8M5 5H3v2a4 4 0 0 0 5 4M19 5h2v2a4 4 0 0 1-5 4"/>',
            'clock' => '<circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/>',
            'bot' => '<rect x="5" y="8" width="14" height="11" rx="3"/><path d="M12 4v4M9 13h.01M15 13h.01M9 16h6"/>',
            'book' => '<path d="M4 5a3 3 0 0 1 3-2h5v17H7a3 3 0 0 0-3 2V5ZM20 5a3 3 0 0 0-3-2h-5v17h5a3 3 0 0 1 3 2V5Z"/>',
            'check' => '<path d="m5 12 4 4L19 6"/>',
            'mail' => '<rect x="3" y="5" width="18" height="14" rx="2"/><path d="m3 7 9 6 9-6"/>',
            'map-pin' => '<path d="M20 10c0 5-8 12-8 12S4 15 4 10a8 8 0 1 1 16 0Z"/><circle cx="12" cy="10" r="2"/>',
            'share' => '<circle cx="18" cy="5" r="3"/><circle cx="6" cy="12" r="3"/><circle cx="18" cy="19" r="3"/><path d="m8.6 10.5 6.8-4M8.6 13.5l6.8 4"/>',
            'edit' => '<path d="M12 20h9M16.5 3.5a2 2 0 0 1 3 3L8 18l-4 1 1-4L16.5 3.5Z"/>',
            'briefcase' => '<rect x="3" y="7" width="18" height="13" rx="2"/><path d="M8 7V4h8v3M3 12h18"/>',
            'ecosystem' => '<rect x="3" y="5" width="8" height="16" rx="1"/><path d="M6 9h2M6 13h2M6 17h2M11 10h10v11H11M15 14h2M15 18h2M7 5V2h10v8"/>',
            'building' => '<rect x="4" y="3" width="16" height="18" rx="2"/><path d="M8 7h2M14 7h2M8 11h2M14 11h2M8 15h2M14 15h2M9 21v-3h6v3"/>',
            'globe' => '<circle cx="12" cy="12" r="9"/><path d="M3 12h18M12 3a15 15 0 0 1 0 18M12 3a15 15 0 0 0 0 18"/>',
            'phone' => '<path d="M22 16.9v3a2 2 0 0 1-2.2 2 19.8 19.8 0 0 1-8.6-3.1 19.5 19.5 0 0 1-6-6A19.8 19.8 0 0 1 2.1 4.2 2 2 0 0 1 4.1 2h3a2 2 0 0 1 2 1.7c.1 1 .4 2 .7 2.9a2 2 0 0 1-.5 2.1L8.1 9.9a16 16 0 0 0 6 6l1.2-1.2a2 2 0 0 1 2.1-.5c.9.3 1.9.6 2.9.7a2 2 0 0 1 1.7 2Z"/>',
            'file-text' => '<path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8Z"/><path d="M14 2v6h6M8 13h8M8 17h6"/>',
            'filter' => '<path d="M4 5h16M7 12h10M10 19h4"/>',
            'chevron-down' => '<path d="m6 9 6 6 6-6"/>',
            'external-link' => '<path d="M15 3h6v6M10 14 21 3M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"/>',
            'info' => '<circle cx="12" cy="12" r="9"/><path d="M12 11v5M12 8h.01"/>',
            'send' => '<path d="m22 2-7 20-4-9-9-4 20-7Z"/><path d="M22 2 11 13"/>',
            'bookmark' => '<path d="M6 4a2 2 0 0 1 2-2h8a2 2 0 0 1 2 2v18l-6-4-6 4V4Z"/>',
            'brain' => '<path d="M9.5 4A3.5 3.5 0 0 0 6 7.5v.6A4 4 0 0 0 7 16v.5a3.5 3.5 0 0 0 5 3.2V4.3A3.5 3.5 0 0 0 9.5 4ZM14.5 4A3.5 3.5 0 0 1 18 7.5v.6a4 4 0 0 1-1 7.9v.5a3.5 3.5 0 0 1-5 3.2V4.3A3.5 3.5 0 0 1 14.5 4Z"/>',
            'users' => '<circle cx="9" cy="8" r="3"/><circle cx="17" cy="9" r="2.5"/><path d="M3 20a6 6 0 0 1 12 0M14 15a5 5 0 0 1 7 5"/>',
            'palette' => '<path d="M12 3a9 9 0 0 0 0 18h1.5a2 2 0 0 0 0-4H12a2 2 0 0 1 0-4h3a6 6 0 0 0 0-12h-3Z"/><circle cx="7.5" cy="10" r=".8"/><circle cx="9" cy="6.5" r=".8"/><circle cx="14" cy="6" r=".8"/>',
            'message-circle' => '<path d="M21 11.5a8.5 8.5 0 0 1-9 8.5 9 9 0 0 1-4-.9L3 21l1.9-5A9 9 0 1 1 21 11.5Z"/>',
            'lightbulb' => '<path d="M9 18h6M10 22h4M8.5 15a7 7 0 1 1 7 0c-.9.7-1.5 1.5-1.5 3h-4c0-1.5-.6-2.3-1.5-3Z"/>',
            'activity' => '<path d="M3 12h4l2-7 4 14 2-7h6"/>',
            'music' => '<path d="M9 18V5l11-2v13M9 9l11-2"/><circle cx="6" cy="18" r="3"/><circle cx="17" cy="16" r="3"/>',
            'leaf' => '<path d="M21 3C12 3 5 7 5 14c0 4 3 7 7 7 7 0 9-9 9-18Z"/><path d="M3 21c4-6 8-9 14-13"/>',
            'graduation-cap' => '<path d="m2 10 10-5 10 5-10 5L2 10Z"/><path d="M6 12v5c3 3 9 3 12 0v-5M22 10v6"/>',
            'x' => '<path d="m6 6 12 12M18 6 6 18"/>',
            'copy' => '<rect x="8" y="8" width="12" height="12" rx="2"/><path d="M16 8V6a2 2 0 0 0-2-2H6a2 2 0 0 0-2 2v8a2 2 0 0 0 2 2h2"/>',
        ];

        if (!isset($paths[$name])) {
            return '';
        }

        return sprintf(
            '<svg width="%1$d" height="%1$d" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">%2$s</svg>',
            $size,
            $paths[$name]
        );
    }
}
