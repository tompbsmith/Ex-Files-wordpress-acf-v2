<?php
/**
 * Ex Files core: client data, the Free Divorce Guide and (later) the portal.
 *
 * This lives in the theme for now because the InstaWP Git deploy only ships the
 * theme folder. Nothing in inc/core depends on the theme's templates, so it can be
 * moved into its own plugin later without changes: copy inc/core and vendor/, and
 * define EXF_CORE_DIR / EXF_CORE_URI in the plugin's main file.
 *
 * @package exfiles
 */

defined( 'ABSPATH' ) || exit;

if ( ! defined( 'EXF_CORE_DIR' ) ) {
	define( 'EXF_CORE_DIR', __DIR__ );
}

// Dompdf and its dependencies (committed, because the deploy doesn't run Composer).
if ( file_exists( EXF_DIR . '/vendor/autoload.php' ) ) {
	require_once EXF_DIR . '/vendor/autoload.php';
}

require EXF_CORE_DIR . '/roles.php';
require EXF_CORE_DIR . '/post-types.php';
require EXF_CORE_DIR . '/storage.php';
require EXF_CORE_DIR . '/settings.php';
require EXF_CORE_DIR . '/pages.php';
require EXF_CORE_DIR . '/pdf.php';
require EXF_CORE_DIR . '/xlsx.php';
require EXF_CORE_DIR . '/cron.php';

require EXF_CORE_DIR . '/guide/defaults.php';
require EXF_CORE_DIR . '/guide/content.php';
require EXF_CORE_DIR . '/guide/logic.php';
require EXF_CORE_DIR . '/guide/submit.php';
require EXF_CORE_DIR . '/guide/front.php';
require EXF_CORE_DIR . '/guide/admin.php';

require EXF_CORE_DIR . '/accounts/users.php';
require EXF_CORE_DIR . '/accounts/emails.php';
require EXF_CORE_DIR . '/accounts/conflict.php';
require EXF_CORE_DIR . '/accounts/conflict-register.php';
require EXF_CORE_DIR . '/accounts/register.php';
require EXF_CORE_DIR . '/accounts/account.php';
require EXF_CORE_DIR . '/accounts/review.php';
require EXF_CORE_DIR . '/accounts/front.php';

require EXF_CORE_DIR . '/commerce/services.php';
require EXF_CORE_DIR . '/commerce/booking.php';
require EXF_CORE_DIR . '/commerce/stripe.php';
require EXF_CORE_DIR . '/commerce/orders.php';
require EXF_CORE_DIR . '/commerce/buy.php';

require EXF_CORE_DIR . '/portal/portal.php';
require EXF_CORE_DIR . '/portal/documents.php';
require EXF_CORE_DIR . '/portal/appointments.php';
require EXF_CORE_DIR . '/portal/account-details.php';
require EXF_CORE_DIR . '/portal/login.php';
require EXF_CORE_DIR . '/portal/admin.php';

require EXF_CORE_DIR . '/questionnaire/schema.php';
require EXF_CORE_DIR . '/questionnaire/data.php';
require EXF_CORE_DIR . '/questionnaire/render.php';
require EXF_CORE_DIR . '/questionnaire/front.php';
require EXF_CORE_DIR . '/questionnaire/outputs.php';
require EXF_CORE_DIR . '/questionnaire/admin.php';

require EXF_CORE_DIR . '/admin/audit.php';
require EXF_CORE_DIR . '/admin/retention.php';
require EXF_CORE_DIR . '/admin/privacy.php';
require EXF_CORE_DIR . '/admin/work.php';
require EXF_CORE_DIR . '/admin/mail.php';
require EXF_CORE_DIR . '/admin/hardening.php';
