<?php
#########################################################################################
#
# Copyright 2010-2011  Maya Studios (http://www.mayastudios.com)
#
# This program is free software: you can redistribute it and/or modify
# it under the terms of the GNU General Public License as published by
# the Free Software Foundation, either version 3 of the License, or
# (at your option) any later version.
#
# This program is distributed in the hope that it will be useful,
# but WITHOUT ANY WARRANTY; without even the implied warranty of
# MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
# GNU General Public License for more details.
#
# You should have received a copy of the GNU General Public License
# along with this program.  If not, see <http://www.gnu.org/licenses/>.
#
#########################################################################################


require_once(dirname(__FILE__).'/../../api/commons.php');


class LinkTargetNotFoundException extends Exception {
  const REASON_DONT_EXIST = 'doesn\'t exist';
  const REASON_NOT_PUBLISHED = 'unpublished';

  private $reason;
  private $title;

  /**
   * Constructor.
   *
   * @param string $reason  the more detailed reason why the link could not be found. Should only be a few
   *    words as they are added to the link title. Defaults to @see REASON_DONT_EXIST. When possible you
   *    should use one of the constants provided by this class.
   * @param string $title  the title of the link that could not be resolved, if known and not explicitely
   *   provided in the interlink. Otherwise "null".
   */
  public function __construct($reason=null, $title=null) {
    parent::__construct();
    if ($reason === null || empty($reason)) {
      $this->reason = self::REASON_DONT_EXIST;
    } else {
      $this->reason = $reason;
    }
    $this->title = $title;
  }

  public function get_reason() {
    return $this->reason;
  }

  public function get_title() {
    return $this->title;
  }
}

?>
