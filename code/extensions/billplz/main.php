<?php

if (!defined('DIR_CORE')) {
    header('Location: static_pages/');
}

$controllers = array(
    'storefront' => array(
        'responses/extension/billplz',
    ),
    'admin' => array(),
);

$models = array(
    'storefront' => array(
        'extension/billplz',
    ),
    'admin' => array(),
);

$languages = array(
    'storefront' => array(
        'billplz/billplz'),
    'admin' => array(
        'billplz/billplz'));

$templates = array(
    'storefront' => array('responses/billplz.tpl'),
    'admin' => array());
