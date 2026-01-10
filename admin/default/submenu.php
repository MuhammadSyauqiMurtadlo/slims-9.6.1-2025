<?php
/**
 * Copyright (C) 2007,2008  Arie Nugraha (dicarve@yahoo.com)
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301  USA
 *
 */

$menu[] = array('Header', __('SHORTCUT'));
$menu['user-profile'] = array(__('Change User Profiles'), MWB.'system/app_user.php?changecurrent=true&action=detail', __('Change Current User Profiles and Password'));
$menu[] = array(__('Shortcut Setting'), MWB.'system/shortcut.php', __('Shortcut Setting'));
$menu[] = array(__('Theme'), MWB.'system/theme.php', __('Configure theme Preferences'));
$menu[] = array(__('Management Tools'), MWB . 'system/management_tools.php', __('Various Management Tools'));

// WhatsApp Notification submenu (converted from HTML to PHP menu entries)
$menu[] = array('Header', __('WhatsApp Notification'));
$menu[] = array(__('Dashboard'), MWB.'wa_notification/index.php', __('Dashboard'));
$menu[] = array(__('Template Pesan'), MWB.'wa_notification/templates.php', __('Template Pesan'));
$menu[] = array(__('Jadwal Notifikasi'), MWB.'wa_notification/schedules.php', __('Jadwal Notifikasi'));
$menu[] = array(__('Log Pengiriman'), MWB.'wa_notification/logs.php', __('Log Pengiriman'));
$menu[] = array(__('Pengaturan'), MWB.'wa_notification/settings.php', __('Pengaturan'));


/*
if (utility::havePrivilege('bibliography', 'r') AND utility::havePrivilege('bibliography', 'w')) {
  $menu[] = array(__('Add New Bibliography'), MWB.'bibliography/index.php?action=detail', __('Add New Bibliographic Data/Catalog'));
}
if (utility::havePrivilege('circulation', 'r') AND utility::havePrivilege('circulation', 'w')) {
  $menu[] = array(__('Start Transaction'), MWB.'circulation/index.php?action=start', __('Start Circulation Transaction Proccess'));
}
if (utility::havePrivilege('circulation', 'r') AND utility::havePrivilege('circulation', 'w')) {
  $menu[] = array(__('Quick Return'), MWB.'circulation/quick_return.php', __('Quick Return Collection'));
}
if (utility::havePrivilege('membership', 'r') AND utility::havePrivilege('membership', 'w')) {
  $menu[] = array(__('Add New Member'), MWB.'membership/index.php?action=detail', __('Add New Library Member Data'));
}
*/