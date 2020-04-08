<?php
/**
 * Paymentsense Plugin for VirtueMart 3
 * Version: 3.0.1
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
 * @version     3.0.1
 * @author      Paymentsense
 * @copyright   2020 Paymentsense Ltd.
 * @license     https://www.gnu.org/licenses/gpl-2.0.html
 */

defined('_JEXEC') or die('Restricted access');
?>
<form action="<?php echo $viewData['url'] ?>" method="post" name="vm_paymentsense_form">
    <?php foreach ($viewData['elements'] as $key => $value) { ?>
			<input type="hidden" name="<?php echo $key ?>" value="<?php echo $value ?>" />
    <?php } ?>
</form>
<script>
    document.getElementsByTagName("body")[0].style.display = "none";
    document.title = "Redirecting to Paymentsense...";
		document.vm_paymentsense_form.submit();
</script>
