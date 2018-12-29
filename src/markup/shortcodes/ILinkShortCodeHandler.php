<?php
require_once(dirname(__FILE__).'/../../api/commons.php');

MSCL_require_once('LinkTargetNotFoundException.php', __FILE__);


/**
 * Interface to resolve an Interlink to a URL.
 *
 * @remarks This is the more simple version of an Interlink. It simply produces a HTML link (ie.
 *   "<a href="...">..</a>"). For creating custom HTML from a Interlink, use "IMacroShortCodeHandler" instead.
 */
interface ILinkShortCodeHandler {
  /**
   * Represents a post in the current blog. The value equals to "$post->post_type".
   */
  const TYPE_POST = 'post';
  /**
   * Represents a page in the current blog. The value equals to "$post->post_type".
   */
  const TYPE_PAGE = 'page';
  /**
   * Represents an attachment. The value equals to "$post->post_type".
   */
  const TYPE_ATTACHMENT = 'attachment';
  /**
   * Anchor to a section heading in the current page.
   */
  const TYPE_SAME_PAGE_ANCHOR = 'same-page-anchor';
  /**
   * Email address
   */
  const TYPE_EMAIL_ADDRESS = 'email-address';

  /**
   * Returns the names of all prefixes handled by this resolver.
   *
   * @return string[] the prefixes. Prefixes are returned without trailing colon.
   */
  public function get_handled_prefixes();

  /**
   * Resolves the specified interlink. Throws a "LinkTargetNotFoundException" if the specified link target
   * doesn't exist.
   *
   * @param int $post_id  the id of the post/page the interlink is contained in
   * @param string $prefix  the prefix to be handled. Will only be one of the prefixes returned by
   *   @see get_handled_prefixes().
   * @param array $params  the parameters as specified in the interlink. The first parameter will be without
   *   the Interlink prefix (and without the trailing colon).
   *
   * @return array Returns an array containing the following items (in this order):
   *   url, title, is_external, type
   *
   *   "url": The URL to which this link points. Must not be empty or null.
   *
   *   "title": can be "null", if the last parameter is to be used as title. This is a convention and should
   *      not be violated. The exception would be when it's absolutely sure that the last parameter is
   *      actually a parameter and not the title. In this case the title should be returned (as non null).
   *
   *   "is_external": indicates whether the link links to an external target or not. External links usually
   *      are opened in a new window while internal ones aren't. External usually means "outside of the
   *      current blog".
   *
   *   "type": should be one of this class' constants (eg. @see TYPE_POST). It can also be any other type but
   *      this type may not be recognized by the user of this class. Usually just added to the link's CSS
   *      classes.
   */
  public function resolve_link($post_id, $prefix, $params);
}
?>
