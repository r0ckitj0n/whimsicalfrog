<?php
/**
 * Button Template Component
 * Comprehensive button system with multiple variants and configurations
 * 
 * Usage Examples:
 * 
 * Basic button:
 * <?php echo render_button(['text' => 'Click Me']); ?>
 * 
 * Primary button with icon:
 * <?php echo render_button([
 *     'text' => 'Save Changes',
 *     'type' => 'primary',
 *     'icon' => 'save',
 *     'size' => 'lg'
 * ]); ?>
 * 
 * Button group:
 * <?php echo render_button_group([
 *     ['text' => 'Edit', 'type' => 'secondary'],
 *     ['text' => 'Delete', 'type' => 'danger']
 * ]); ?>
 */

/**
 * Render a single button
 */
function render_button($config = []) {
    // Default configuration
    $defaults = [
        'text' => 'Button',
        'type' => 'primary', // primary, secondary, danger, success, warning, info, light, dark
        'size' => 'md', // xs, sm, md, lg, xl
        'variant' => 'solid', // solid, outline, ghost, link
        'icon' => null, // Icon name or SVG
        'icon_position' => 'left', // left, right, only
        'href' => null, // If provided, renders as <a> tag
        'onclick' => null,
        'disabled' => false,
        'loading' => false,
        'full_width' => false,
        'attributes' => [], // Additional HTML attributes
        'classes' => [], // Additional CSS classes
        'id' => null,
        'form' => null, // Form ID for submit buttons
        'submit' => false, // Whether this is a submit button
        'target' => null, // For links: _blank, _self, etc.
        'aria_label' => null,
        'tooltip' => null
    ];
    
    $config = array_merge($defaults, $config);
    
    // Build CSS classes
    $classes = ['btn'];
    
    // Type classes
    if ($config['variant'] === 'outline') {
        $classes[] = 'btn-outline';
        $classes[] = 'btn-' . $config['type'];
    } elseif ($config['variant'] === 'ghost') {
        $classes[] = 'btn-ghost';
    } elseif ($config['variant'] === 'link') {
        $classes[] = 'btn-link';
    } else {
        $classes[] = 'btn-' . $config['type'];
    }
    
    // Size classes
    if ($config['size'] !== 'md') {
        $classes[] = 'btn-' . $config['size'];
    }
    
    // Special classes
    if ($config['full_width']) {
        $classes[] = 'btn-block';
    }
    
    if ($config['loading']) {
        $classes[] = 'btn-loading';
    }
    
    if ($config['icon'] && $config['icon_position'] === 'only') {
        $classes[] = 'btn-icon';
    }
    
    if ($config['icon']) {
        $classes[] = 'btn-with-icon';
    }
    
    // Add custom classes
    $classes = array_merge($classes, $config['classes']);
    
    // Build attributes
    $attributes = [];
    
    if ($config['id']) {
        $attributes['id'] = $config['id'];
    }
    
    if ($config['disabled']) {
        $attributes['disabled'] = 'disabled';
        $attributes['aria-disabled'] = 'true';
    }
    
    if ($config['aria_label']) {
        $attributes['aria-label'] = $config['aria_label'];
    }
    
    if ($config['tooltip']) {
        $attributes['title'] = $config['tooltip'];
    }
    
    if ($config['onclick']) {
        $attributes['onclick'] = $config['onclick'];
    }
    
    // Merge custom attributes
    $attributes = array_merge($attributes, $config['attributes']);
    
    // Build the button content
    $content = '';
    
    if ($config['loading']) {
        $content .= '<span class="btn-text">';
    }
    
    // Add icon (left or only)
    if ($config['icon'] && in_array($config['icon_position'], ['left', 'only'])) {
        $content .= render_icon($config['icon']);
    }
    
    // Add text (unless icon-only)
    if ($config['icon_position'] !== 'only') {
        $content .= htmlspecialchars($config['text']);
    }
    
    // Add icon (right)
    if ($config['icon'] && $config['icon_position'] === 'right') {
        $content .= render_icon($config['icon']);
    }
    
    if ($config['loading']) {
        $content .= '</span>';
    }
    
    // Render as link or button
    if ($config['href']) {
        $attributes['href'] = $config['href'];
        if ($config['target']) {
            $attributes['target'] = $config['target'];
        }
        $tag = 'a';
        $attributes['role'] = 'button';
    } else {
        $tag = 'button';
        $attributes['type'] = $config['submit'] ? 'submit' : 'button';
        if ($config['form']) {
            $attributes['form'] = $config['form'];
        }
    }
    
    // Build attribute string
    $attr_string = '';
    foreach ($attributes as $key => $value) {
        $attr_string .= ' ' . htmlspecialchars($key) . '="' . htmlspecialchars($value) . '"';
    }
    
    return "<{$tag} class=\"" . implode(' ', $classes) . "\"{$attr_string}>{$content}</{$tag}>";
}

/**
 * Render a button group
 */
function render_button_group($buttons, $config = []) {
    $defaults = [
        'vertical' => false,
        'classes' => [],
        'attributes' => []
    ];
    
    $config = array_merge($defaults, $config);
    
    $classes = ['btn-group'];
    if ($config['vertical']) {
        $classes[] = 'btn-group-vertical';
    }
    $classes = array_merge($classes, $config['classes']);
    
    $attr_string = '';
    foreach ($config['attributes'] as $key => $value) {
        $attr_string .= ' ' . htmlspecialchars($key) . '="' . htmlspecialchars($value) . '"';
    }
    
    $html = "<div class=\"" . implode(' ', $classes) . "\"{$attr_string} role=\"group\">";
    
    foreach ($buttons as $button_config) {
        $html .= render_button($button_config);
    }
    
    $html .= "</div>";
    
    return $html;
}

/**
 * Render common button combinations
 */
function render_form_buttons($config = []) {
    $defaults = [
        'submit_text' => 'Save',
        'cancel_text' => 'Cancel',
        'cancel_href' => null,
        'show_cancel' => true,
        'submit_loading' => false,
        'submit_disabled' => false
    ];
    
    $config = array_merge($defaults, $config);
    
    $buttons = [];
    
    if ($config['show_cancel']) {
        $buttons[] = [
            'text' => $config['cancel_text'],
            'type' => 'secondary',
            'href' => $config['cancel_href']
        ];
    }
    
    $buttons[] = [
        'text' => $config['submit_text'],
        'type' => 'primary',
        'submit' => true,
        'loading' => $config['submit_loading'],
        'disabled' => $config['submit_disabled']
    ];
    
    return render_button_group($buttons, ['classes' => ['form-buttons']]);
}

function render_crud_buttons($config = []) {
    $defaults = [
        'show_edit' => true,
        'show_delete' => true,
        'show_view' => false,
        'edit_href' => '#',
        'delete_href' => '#',
        'view_href' => '#',
        'delete_confirm' => true
    ];
    
    $config = array_merge($defaults, $config);
    
    $buttons = [];
    
    if ($config['show_view']) {
        $buttons[] = [
            'text' => 'View',
            'type' => 'info',
            'size' => 'sm',
            'href' => $config['view_href'],
            'icon' => 'eye'
        ];
    }
    
    if ($config['show_edit']) {
        $buttons[] = [
            'text' => 'Edit',
            'type' => 'secondary',
            'size' => 'sm',
            'href' => $config['edit_href'],
            'icon' => 'edit'
        ];
    }
    
    if ($config['show_delete']) {
        $onclick = $config['delete_confirm'] 
            ? "return confirm('Are you sure you want to delete this item?')" 
            : null;
            
        $buttons[] = [
            'text' => 'Delete',
            'type' => 'danger',
            'size' => 'sm',
            'href' => $config['delete_href'],
            'icon' => 'trash',
            'onclick' => $onclick
        ];
    }
    
    return render_button_group($buttons, ['classes' => ['crud-buttons']]);
}

/**
 * Render icon (simple icon system)
 */
function render_icon($icon) {
    // Common icons as SVG
    $icons = [
        'save' => '<svg width="16" height="16" fill="currentColor" viewBox="0 0 16 16"><path d="M2 1a1 1 0 0 0-1 1v12a1 1 0 0 0 1 1h12a1 1 0 0 0 1-1V2a1 1 0 0 0-1-1H9.5a1 1 0 0 0-1 1v7.293l2.646-2.647a.5.5 0 0 1 .708.708l-3.5 3.5a.5.5 0 0 1-.708 0l-3.5-3.5a.5.5 0 1 1 .708-.708L7.5 9.293V2a2 2 0 0 1 2-2H14a2 2 0 0 1 2 2v12a2 2 0 0 1-2 2H2a2 2 0 0 1-2-2V2a2 2 0 0 1 2-2h2.5a.5.5 0 0 1 0 1H2z"/></svg>',
        'edit' => '<svg width="16" height="16" fill="currentColor" viewBox="0 0 16 16"><path d="M12.146.146a.5.5 0 0 1 .708 0l3 3a.5.5 0 0 1 0 .708L10.5 8.207l-3-3L12.146.146zM11.207 9l-3-3L2.5 11.707V13.5a.5.5 0 0 0 .5.5h1.793L11.207 9zm.353-3.354l-3-3L9.914 1.293a.5.5 0 0 1-.708.708L8.5 2.707l3 3 .707-.707a.5.5 0 0 1 .708.708l-.707.707z"/></svg>',
        'delete' => '<svg width="16" height="16" fill="currentColor" viewBox="0 0 16 16"><path d="M5.5 5.5A.5.5 0 0 1 6 6v6a.5.5 0 0 1-1 0V6a.5.5 0 0 1 .5-.5zm2.5 0a.5.5 0 0 1 .5.5v6a.5.5 0 0 1-1 0V6a.5.5 0 0 1 .5-.5zm3 .5a.5.5 0 0 0-1 0v6a.5.5 0 0 0 1 0V6z"/><path fill-rule="evenodd" d="M14.5 3a1 1 0 0 1-1 1H13v9a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V4h-.5a1 1 0 0 1-1-1V2a1 1 0 0 1 1-1H6a1 1 0 0 1 1-1h2a1 1 0 0 1 1 1h3.5a1 1 0 0 1 1 1v1zM4.118 4L4 4.059V13a1 1 0 0 0 1 1h6a1 1 0 0 0 1-1V4.059L11.882 4H4.118zM2.5 3V2h11v1h-11z"/></svg>',
        'trash' => '<svg width="16" height="16" fill="currentColor" viewBox="0 0 16 16"><path d="M5.5 5.5A.5.5 0 0 1 6 6v6a.5.5 0 0 1-1 0V6a.5.5 0 0 1 .5-.5zm2.5 0a.5.5 0 0 1 .5.5v6a.5.5 0 0 1-1 0V6a.5.5 0 0 1 .5-.5zm3 .5a.5.5 0 0 0-1 0v6a.5.5 0 0 0 1 0V6z"/><path fill-rule="evenodd" d="M14.5 3a1 1 0 0 1-1 1H13v9a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V4h-.5a1 1 0 0 1-1-1V2a1 1 0 0 1 1-1H6a1 1 0 0 1 1-1h2a1 1 0 0 1 1 1h3.5a1 1 0 0 1 1 1v1zM4.118 4L4 4.059V13a1 1 0 0 0 1 1h6a1 1 0 0 0 1-1V4.059L11.882 4H4.118zM2.5 3V2h11v1h-11z"/></svg>',
        'eye' => '<svg width="16" height="16" fill="currentColor" viewBox="0 0 16 16"><path d="M16 8s-3-5.5-8-5.5S0 8 0 8s3 5.5 8 5.5S16 8 16 8zM1.173 8a13.133 13.133 0 0 1 1.66-2.043C4.12 4.668 5.88 3.5 8 3.5c2.12 0 3.879 1.168 5.168 2.457A13.133 13.133 0 0 1 14.828 8c-.058.087-.122.183-.195.288-.335.48-.83 1.12-1.465 1.755C11.879 11.332 10.119 12.5 8 12.5c-2.12 0-3.879-1.168-5.168-2.457A13.134 13.134 0 0 1 1.172 8z"/><path d="M8 5.5a2.5 2.5 0 1 0 0 5 2.5 2.5 0 0 0 0-5zM4.5 8a3.5 3.5 0 1 1 7 0 3.5 3.5 0 0 1-7 0z"/></svg>',
        'plus' => '<svg width="16" height="16" fill="currentColor" viewBox="0 0 16 16"><path d="M8 4a.5.5 0 0 1 .5.5v3h3a.5.5 0 0 1 0 1h-3v3a.5.5 0 0 1-1 0v-3h-3a.5.5 0 0 1 0-1h3v-3A.5.5 0 0 1 8 4z"/></svg>',
        'minus' => '<svg width="16" height="16" fill="currentColor" viewBox="0 0 16 16"><path d="M4 8a.5.5 0 0 1 .5-.5h7a.5.5 0 0 1 0 1h-7A.5.5 0 0 1 4 8z"/></svg>',
        'check' => '<svg width="16" height="16" fill="currentColor" viewBox="0 0 16 16"><path d="M10.97 4.97a.235.235 0 0 0-.02.022L7.477 9.417 5.384 7.323a.75.75 0 0 0-1.06 1.06L6.97 11.03a.75.75 0 0 0 1.079-.02l3.992-4.99a.75.75 0 0 0-1.071-1.05z"/></svg>',
        'x' => '<svg width="16" height="16" fill="currentColor" viewBox="0 0 16 16"><path d="M4.646 4.646a.5.5 0 0 1 .708 0L8 7.293l2.646-2.647a.5.5 0 0 1 .708.708L8.707 8l2.647 2.646a.5.5 0 0 1-.708.708L8 8.707l-2.646 2.647a.5.5 0 0 1-.708-.708L7.293 8 4.646 5.354a.5.5 0 0 1 0-.708z"/></svg>',
        'download' => '<svg width="16" height="16" fill="currentColor" viewBox="0 0 16 16"><path d="M.5 9.9a.5.5 0 0 1 .5.5v2.5a1 1 0 0 0 1 1h12a1 1 0 0 0 1-1v-2.5a.5.5 0 0 1 1 0v2.5a2 2 0 0 1-2 2H2a2 2 0 0 1-2-2v-2.5a.5.5 0 0 1 .5-.5z"/><path d="M7.646 11.854a.5.5 0 0 0 .708 0l3-3a.5.5 0 0 0-.708-.708L8.5 10.293V1.5a.5.5 0 0 0-1 0v8.793L5.354 8.146a.5.5 0 1 0-.708.708l3 3z"/></svg>',
        'upload' => '<svg width="16" height="16" fill="currentColor" viewBox="0 0 16 16"><path d="M.5 9.9a.5.5 0 0 1 .5.5v2.5a1 1 0 0 0 1 1h12a1 1 0 0 0 1-1v-2.5a.5.5 0 0 1 1 0v2.5a2 2 0 0 1-2 2H2a2 2 0 0 1-2-2v-2.5a.5.5 0 0 1 .5-.5z"/><path d="M7.646 1.146a.5.5 0 0 1 .708 0l3 3a.5.5 0 0 1-.708.708L8.5 2.707V11.5a.5.5 0 0 1-1 0V2.707L5.354 4.854a.5.5 0 1 1-.708-.708l3-3z"/></svg>',
    ];
    
    if (isset($icons[$icon])) {
        return $icons[$icon];
    }
    
    // If icon starts with <svg, assume it's custom SVG
    if (strpos($icon, '<svg') === 0) {
        return $icon;
    }
    
    // Otherwise, return empty string
    return '';
}

// Example usage functions for common scenarios
function add_button($href = '#', $text = 'Add New') {
    return render_button([
        'text' => $text,
        'type' => 'primary',
        'icon' => 'plus',
        'href' => $href
    ]);
}

function save_button($loading = false, $disabled = false) {
    return render_button([
        'text' => 'Save Changes',
        'type' => 'primary',
        'icon' => 'save',
        'submit' => true,
        'loading' => $loading,
        'disabled' => $disabled
    ]);
}

function cancel_button($href = null) {
    return render_button([
        'text' => 'Cancel',
        'type' => 'secondary',
        'href' => $href
    ]);
}

function delete_button($href = '#', $confirm = true) {
    return render_button([
        'text' => 'Delete',
        'type' => 'danger',
        'icon' => 'trash',
        'href' => $href,
        'onclick' => $confirm ? "return confirm('Are you sure?')" : null
    ]);
}
?> 