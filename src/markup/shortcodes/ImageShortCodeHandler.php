<?php
use MSCL\FileInfo\FileInfoException;
use MSCL\FileInfo\FileNotFoundException;
use MSCL\FileInfo\AbstractFileInfo;
use MSCL\FileInfo\ImageFileInfo;

require_once(dirname(__FILE__) . '/../../api/commons.php');

MSCL_require_once('IMacroShortCodeHandler.php', __FILE__);

class ImageShortCodeHandler implements IMacroShortCodeHandler
{
    const DEFAULT_THUMB_WIDTH = 128;
    const DEFAULT_THUMB_HEIGHT = 96;

    public function get_handled_prefixes()
    {
        return array('img', 'image');
    }

    public function handle_macro($link_resolver, $prefix, $params, $generate_html, $after_text)
    {
        $post_id = get_the_ID();
        list($is_attachment, $ref) = $this->get_file_info($params[0], $post_id);
        if ($is_attachment && $ref === null)
        {
            $html = self::generate_error_html($params[0], true);
        }
        else
        {
            switch ($prefix)
            {
                case 'img':
                case 'image':
                    $html = $this->generate_img_tag($link_resolver, $is_attachment, $ref, $params, $generate_html);
                    break;
                default:
                    throw new Exception('Unexpected prefix: ' . $prefix);
            }
        }

        return $html . $after_text;
    }

    private static function generate_error_html($message, $not_found = false)
    {
        if ($not_found)
        {
            return AbstractTextMarkup::generate_error_html('Attachment "' . $message . '" not found', 'error-attachment-not-found');
        }

        return AbstractTextMarkup::generate_error_html($message);
    }

    private function generate_img_tag($link_resolver, $is_attachment, $ref, $params, $generate_html)
    {
        $link            = '';
        $link_params     = array();
        $display_caption = false;
        $no_caption      = false;
        $is_thumb        = false;
        $alignment       = '';
        $title           = '';
        $img_size_attr   = '';

        if (!$generate_html)
        {
            if ($is_attachment)
            {
                return wp_get_attachment_url($ref);
            }
            else
            {
                return $ref;
            }
        }

        if (!$is_attachment
            && AbstractFileInfo::isRemoteFileStatic($ref)
            && AbstractFileInfo::isRemoteFileSupportAvailable()
            && !AbstractFileInfo::isUrlProtocolSupported($ref)
        )
        {
            // Remote image whose protocol isn't supported. Create a good looking error message here.
            return self::generate_error_html("The protocol for remote file '" . $ref . "' isn't supported.");
        }

        // Identify parameters
        foreach ($params as $key => $param)
        {
            $param = trim($param);
            if (empty($param) || $key == 0)
            {
                // Skip empty params. Also skip the first element as it always contains the image address and
                // otherwise could be used as title (if it's also the last parameter).
                continue;
            }

            if (substr($param, 0, 5) == 'link=')
            {
                // NOTE: We don't allow overwriting the link for "thumb". That's what thumb is for.
                if (!$is_thumb)
                {
                    $link = substr($param, 5);
                }
            } // -------------------------------
            else if ($param == 'thumb')
            {
                // Thumbnails always have a link to their fullsize image.
                $is_thumb = true;
                $link     = 'source';
            }
            else if ($param == 'caption')
            {
                $display_caption = true;
            }
            else if ($param == 'nocaption')
            {
                $no_caption = true;
            }
            else if ($param == 'left' || $param == 'right' || $param == 'center')
            {
                $alignment = $param;
            }
            else if ($param == 'small' || $param == 'medium' || $param == 'large' || substr($param, - 2) == 'px')
            {
                $img_size_attr = $param;
            }
            else if ($param == 'big')
            {
                # "big" is just an alias for "large"
                $img_size_attr = 'large';
            }
            else
            {
                if ($key == count($params) - 1)
                {
                    // if this is the last parameter and not one of the types above, assume it's the title
                    $title = $param;
                }
                else if (!empty($link) && !$is_thumb)
                {
                    // if not the parameters may belong to the link
                    // note that the following code isn't completely correct
                    $link_params[] = $param;
                }
            }
        }

        // display caption if the user specified one
        if (!empty($title) && BlogTextSettings::display_caption_if_provided() && !$no_caption)
        {
            $display_caption = true;
        }

        //
        // resolve link
        //
        if ($link == 'source')
        {
            // link to source image
            if ($is_attachment)
            {
                $link = wp_get_attachment_url($ref);
            }
            else
            {
                $link = $ref;
            }
        }
        else if (!empty($link) && !$is_thumb)
        {
            list($prefix, $link) = $link_resolver->get_prefix($link);
            array_unshift($link_params, $link); // place the link at the beginning of the params
            $link = $link_resolver->resolve_link($prefix, $link_params, false, '', '');
        }

        //
        // title
        //
        $alt_text = '';
        if ($is_attachment)
        {
            // Get image caption as stored in the database - if the attachment is an image
            if (empty($title))
            {
                list($title, $alt_text) = MarkupUtil::get_attachment_image_titles($ref);
            }
            else
            {
                $alt_text = MarkupUtil::get_attachment_image_alt_text($ref);
            }
        }
        $title = htmlspecialchars(trim($title));
        if (!empty($alt_text))
        {
            $alt_text = htmlspecialchars($alt_text);
        }
        else
        {
            $alt_text = $title;
        }

        //
        // size and alignment
        //
        if ($is_thumb && empty($img_size_attr))
        {
            // Set default thumb size
            if ($alignment == 'center')
            {
                // Use "large" when the thumbnail is centered.
                $img_size_attr = 'large';
            }
            else
            {
                // NOTE: We assume "small" here as this is what Wordpress calls "thumbnail" size.
                $img_size_attr = 'small';
            }
        }

        if (empty($alignment) && ($is_thumb || $display_caption))
        {
            if ($img_size_attr == 'small')
            {
                $alignment = BlogTextSettings::get_default_small_img_alignment();
            }
            else if ($img_size_attr == 'medium' || $img_size_attr == 'large')
            {
                $alignment = 'center';
            }
            // Don't align images without a named size (like "200px" or no size at all).
        }

        // Default values: If width is zero, it's omitted from the HTML code.
        $max_img_width  = 0;

        try
        {
            if ($is_attachment)
            {
                $img_url = wp_get_attachment_url($ref);
            }
            else
            {
                $img_url = $ref;
            }

            if (empty($img_size_attr))
            {
                if ($display_caption && !empty($title))
                {
                    # NOTE: If the image's caption is to be displayed, we need the image's width (see below).
                    $img_size = self::getImageSize($is_attachment, $ref);
                    if ($img_size !== false)
                    {
                        $max_img_width  = $img_size[0];
                    }
                }
            }
            else
            {
                // Width is specified.
                if (substr($img_size_attr, -2) == 'px')
                {
                    // Actual size - not a symbolic one.
                    $max_img_width = (int) substr($img_size_attr, 0, -2);
                }
                else
                {
                    list($max_img_width, $img_height) = self::resolve_size_name($img_size_attr);
                }
            }
        }
        catch (FileNotFoundException $e)
        {
            return self::generate_error_html($e->getFilePath(), true);
        }
        catch (FileInfoException $e)
        {
            return self::generate_error_html($e->getMessage());
        }
        catch (MSCL_ThumbnailException $e)
        {
            return self::generate_error_html($e->getMessage());
        }

        #
        # Generate HTML code
        #
        $html = '<img class="wp-post-image" src="' . $img_url . '" title="' . $title . '" alt="' . $alt_text . '"';
        // image width and height may be "null" for remote images for performance reasons. We let the browser
        // determine their size.
        if ($max_img_width > 0)
        {
            $html .= ' style="max-width: ' . $max_img_width . 'px;"';
        }
        $html .= '/>';

        // Add link
        if (!empty($link))
        {
            $html = '<a href="' . $link . '"'
                    . ($is_attachment ? ' rel="attachment"' : '')
                    . (!empty($title) ? " title=\"$title\"" : '')
                    . '>' . $html . '</a>';
        }

        # Display caption
        if ($display_caption && !empty($title))
        {
            $align_style = !empty($alignment) ? (' align-' . $alignment . ' image-frame-align-' . $alignment) : '';

            # NOTE: We need to specify the width here so that long titles break properly. Note also that the width needs
            #   to be specified on the container (image-frame) to prevent it from expanding to the full page width.
            $html = '<div class="image-frame' . $align_style . '" style="max-width:' . $max_img_width . 'px;">'
                    . '<div class="image">' . $html . '</div>'
                    . '<div class="image-caption">' . $title . '</div>'
                    . '</div>';
        }
        else if (!empty($alignment))
        {
            $html = '<div class="align-' . $alignment . ' image-align-' . $alignment . '">' . $html . '</div>';
        }

        return $html;
    }

    /**
     * Checks whether the specified reference is a url or an attachment.
     *
     * @param string|int $ref
     * @param int        $post_id
     *
     * @return array returns array(is_attachment, id/url)
     */
    private function get_file_info($ref, $post_id)
    {
        if (MarkupUtil::is_url($ref))
        {
            return array(false, $ref);
        }
        else
        {
            return array(true, $this->get_attachment_id($ref, $post_id));
        }
    }

    private function get_attachment_id($ref, $post_id)
    {
        // NOTE: "is_numeric" also checks for numeric strings (which "is_int()" doesn't). So don't use "is_int()"
        //  here.
        if (is_numeric($ref))
        {
            // ID
            return MarkupUtil::is_attachment($ref) ? $ref : null;
        }
        else
        {
            return MarkupUtil::get_attachment_id($ref, $post_id);
        }
    }

    private static function getImageSize($isAttachment, $ref)
    {
        try
        {
            $img_path = $isAttachment ? get_attached_file($ref, true) : $ref;

            $info = ImageFileInfo::get_instance($img_path);

            return array($info->get_width(), $info->get_height());
        }
        catch (FileInfoException $e)
        {
            // Media information not available; don't specify size
            log_error($e->getMessage(), 'media info not available');

            return false;
        }
    }

    /**
     * Returns the maximum size (in pixels) for specified size (name; eg. "small", "medium", "large").
     *
     * @param string $size the size as name (eg. "small", "medium", "large")
     *
     * @return array Returns "list($max_width, $max_height)". Either or both can be "0", if they're not
     *   specified, meaning that the width or height isn't restricted for this size.
     *
     * @throws Exception thrown if an invalid size name has been specified.
     */
    private static function resolve_size_name($size)
    {
        //
        // NOTE: This method is based on "image_constrain_size_for_editor()" defined in "media.php" in Wordpress.
        //
        global $_wp_additional_image_sizes;

        if ($size == 'small')
        {
            $max_width = intval(get_option('thumbnail_size_w'));
            $max_height = intval(get_option('thumbnail_size_h'));
            // last chance thumbnail size defaults
            if (!$max_width)
            {
                $max_width = self::DEFAULT_THUMB_WIDTH;
            }
            if (!$max_height)
            {
                $max_height = self::DEFAULT_THUMB_HEIGHT;
            }
            // Fix the size name for "apply_filters()" below.
            $size = 'thumb';
        }
        elseif ($size == 'medium')
        {
            $max_width = intval(get_option('medium_size_w'));
            $max_height = intval(get_option('medium_size_h'));
        }
        elseif ($size == 'large')
        {
            $max_width = intval(get_option('large_size_w'));
            $max_height = intval(get_option('large_size_h'));
        }
        elseif (   isset($_wp_additional_image_sizes)
                && count($_wp_additional_image_sizes)
                && in_array($size, array_keys($_wp_additional_image_sizes)))
        {
            $max_width = intval($_wp_additional_image_sizes[$size]['width']);
            $max_height = intval($_wp_additional_image_sizes[$size]['height']);
        }
        else
        {
            throw new Exception("Invalid image size: ".$size);
        }

        $content_width = self::get_content_width();
        if ($content_width != 0 && $max_width > $content_width)
        {
            $max_width = $content_width;
        }

        list($max_width, $max_height) = apply_filters('editor_max_image_size', array($max_width, $max_height), $size);
        if ($max_width < 1)
        {
            $max_width = 0;
        }
        if ($max_height < 1)
        {
            $max_height = 0;
        }

        return array($max_width, $max_height);
    }

    /**
     * Returns the width available for a post's content in pixels. Returns "0" (zero), if the content width is
     * unknown.
     */
    private static function get_content_width() {
        global $content_width;

        if (is_numeric($content_width))
        {
            $width = (int)$content_width;
            if ($width > 0)
            {
                return $width;
            }
        }

        return 0;
    }
}
