<?php
/**
 * Template Name: Sections
 *
 * Any page using this template is built from the same flexible content sections as the homepage.
 *
 * @package exfiles
 */

get_header();
exf_render_sections( get_queried_object_id() );
get_footer();
