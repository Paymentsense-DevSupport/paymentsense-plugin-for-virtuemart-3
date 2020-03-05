<?php
/**
 * Paymentsense Plugin for VirtueMart 3
 * Version: 3.0.0
 *
 * This program is free software; you can redistribute it and/or
 * modify it under the terms of the GNU General Public License
 * as published by the Free Software Foundation; either version 2
 * of the License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * @version     3.0.0
 * @author      Paymentsense
 * @copyright   2020 Paymentsense Ltd.
 * @license     https://www.gnu.org/licenses/gpl-2.0.html
 */

defined('_JEXEC') or die('Restricted access');
?>
<p>There has been a problem with your payment. The reason for the decline is: <b><?php echo $viewData['message'] ?></b><p>
<p>Please check your billing address and card details. Alternatively, try a different credit/debit card.</p>
<p><a href="<?php echo $viewData['cart_url'] ?>">Click here to return to the checkout</a>.</p>
