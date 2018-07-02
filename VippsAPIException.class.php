<?php
/* This file just creates some exception types for error handling. IOK 2018-04-18

   This file is part of the WordPress plugin Checkout with Vipps for WooCommerce
   Copyright (C) 2018 WP Hosting AS

   Checkout with Vipps for WooCommerce is free software: you can redistribute it and/or modify
   it under the terms of the GNU Affero General Public License as published by
   the Free Software Foundation, either version 3 of the License, or
   (at your option) any later version.

   Checkout with Vipps for WooCommerce is distributed in the hope that it will be useful,
   but WITHOUT ANY WARRANTY; without even the implied warranty of
   MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
   GNU Affero General Public License for more details.

   You should have received a copy of the GNU Affero General Public License
   along with this program.  If not, see <http://www.gnu.org/licenses/>.
 */


if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly
}
class VippsAPIException extends Exception {
    public $responsecode = null;
}

// This is for 502 bad gateway, timeouts and other errors we can expect to recover from
class TemporaryVippsAPIException extends VippsAPIException {
}

// This is for non-temporary problems with the keys and so forth
class VippsAPIConfigurationException extends VippsAPIException {
}

