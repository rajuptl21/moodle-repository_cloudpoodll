<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

namespace repository_cloudpoodll;

defined('MOODLE_INTERNAL') || die();


/**
 * Class constants
 *
 * @package    repository_cloudpoodll
 * @copyright  2025 Justin Hunt <justin@poodll.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class constants {

    //component name, db tables, things that define app
    const M_COMPONENT = 'repository_cloudpoodll';

    const M_SHORTNAME = 'cloudpoodll';
    const M_DEFAULT_CLOUDPOODLL = "cloud.poodll.com";

    const CLOUDPOODLL_OPTION = -1;
    const AIPROVIDER_COMPONENT = 'aiplacement_poodll';
    const AIPROVIDER_ACTION = 'generate_wordcards_image';

}
