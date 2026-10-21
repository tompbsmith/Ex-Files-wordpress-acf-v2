<?php
/**
 * Default Free Divorce Guide content, taken from the client's precedent document
 * ("The Ex Files Free Divorce Guide Precedent.docx") and the conditional form sheet
 * ("Free Divorce Guide - Conditional Form.xlsx").
 *
 * The wording below is only a starting point: everything except the questions and
 * their branching can be edited in Ex Files → Divorce Guide Content.
 *
 * @package exfiles
 */

defined( 'ABSPATH' ) || exit;

/**
 * The questions, in the order they can appear. Option values are stored; labels are shown.
 *
 * Branching lives in exf_guide_next_step() (logic.php), next to the rules it implements.
 */
function exf_guide_questions() {
	return array(
		'q1'  => array(
			'label'   => __( 'Are you separating or thinking about separating from your partner/spouse?', 'exfiles' ),
			'options' => array(
				'yes' => __( 'Yes', 'exfiles' ),
				'no'  => __( 'No', 'exfiles' ),
			),
		),
		'q2'  => array(
			'label'   => __( 'Are you married?', 'exfiles' ),
			'options' => array(
				'yes' => __( 'Yes', 'exfiles' ),
				'no'  => __( 'No', 'exfiles' ),
			),
		),
		'q2a' => array(
			'label'   => __( 'You have told us that you are not married. Are you looking for advice about your financial position following separation?', 'exfiles' ),
			'options' => array(
				'yes' => __( 'Yes', 'exfiles' ),
				'no'  => __( 'No', 'exfiles' ),
			),
		),
		'q3'  => array(
			'label'   => __( 'Are you eligible to apply for a divorce in England & Wales?', 'exfiles' ),
			'options' => array(
				'yes'    => __( 'Yes', 'exfiles' ),
				'no'     => __( 'No', 'exfiles' ),
				'unsure' => __( 'I am not sure', 'exfiles' ),
			),
			'help'    => true, // Shows the jurisdiction routes and tooltips.
		),
		'q4'  => array(
			'label'   => __( 'Have you started divorce proceedings?', 'exfiles' ),
			'options' => array(
				'yes' => __( 'Yes', 'exfiles' ),
				'no'  => __( 'No', 'exfiles' ),
			),
		),
		'q5'  => array(
			'label'   => __( 'What stage have you reached in your divorce?', 'exfiles' ),
			'options' => array(
				'sole_no_response' => __( 'Sole application issued but my ex has not responded', 'exfiles' ),
				'sole_20w'         => __( 'Sole application issued, my ex has responded and we are in the 20-week period', 'exfiles' ),
				'joint_20w'        => __( 'Joint application issued and we are in the 20-week period', 'exfiles' ),
				'sole_conditional' => __( 'Sole application made and I have received my Conditional Order', 'exfiles' ),
				'joint_conditional' => __( 'Joint application made and we have received our Conditional Order', 'exfiles' ),
				'final'            => __( 'Final Order / Decree Absolute has been granted', 'exfiles' ),
			),
		),
		'q5a' => array(
			'label'   => __( 'Would you like to apply for the divorce yourself, or would you prefer a solicitor to deal with the divorce application for you?', 'exfiles' ),
			'options' => array(
				'myself'    => __( 'I want to apply myself', 'exfiles' ),
				'solicitor' => __( 'I want a solicitor to deal with it', 'exfiles' ),
				'unsure'    => __( 'I am not sure', 'exfiles' ),
			),
		),
		'q6'  => array(
			'label'   => __( 'What stage have you reached in resolving the financial claims arising out of your marriage?', 'exfiles' ),
			'options' => array(
				'no_assets' => __( 'We do not have any finances to divide', 'exfiles' ),
				'guidance'  => __( 'We have not started discussing finances / I need guidance', 'exfiles' ),
				'fairness'  => __( 'We have reached an agreement but I am not sure if it is fair', 'exfiles' ),
				'binding'   => __( 'We have reached an agreement and I want to make it legally binding', 'exfiles' ),
			),
		),
	);
}

/**
 * Services offered at the end of the guide (Q7). Keys are stored.
 */
function exf_guide_services() {
	return array(
		'know_your_position' => __( 'Know Your Position', 'exfiles' ),
		'check_your_deal'    => __( 'Check Your Deal', 'exfiles' ),
		'make_it_legal'      => __( 'Make It Legal', 'exfiles' ),
	);
}

/**
 * Tags, the answers that trigger them, and their default paragraphs (the sheet's "Guide Mapping" tab).
 */
function exf_guide_default_mapping() {
	return array(
		'DIVORCE_ELIGIBILITY'      => array( __( 'Married + eligible', 'exfiles' ), array( 'P01', 'P02' ) ),
		'NOT_EW'                   => array( __( 'Married + not eligible / unsure', 'exfiles' ), array( 'P03' ) ),
		'DIVORCE_OPTIONS'          => array( __( 'Not started divorce', 'exfiles' ), array( 'P04', 'P05', 'P06' ) ),
		'STAGE_ISSUED_NO_RESPONSE' => array( __( 'Sole issued + no response', 'exfiles' ), array( 'P07' ) ),
		'STAGE_SOLE_20W'           => array( __( 'Sole + response + 20-week period', 'exfiles' ), array( 'P08', 'P10', 'P11' ) ),
		'STAGE_JOINT_20W'          => array( __( 'Joint + 20-week period', 'exfiles' ), array( 'P08', 'P10', 'P11', 'P12' ) ),
		'STAGE_SOLE_CONDITIONAL'   => array( __( 'Sole + Conditional Order', 'exfiles' ), array( 'P09', 'P10', 'P11', 'P13' ) ),
		'STAGE_JOINT_CONDITIONAL'  => array( __( 'Joint + Conditional Order', 'exfiles' ), array( 'P09', 'P10', 'P11', 'P12' ) ),
		'STAGE_FINAL'              => array( __( 'Final Order granted', 'exfiles' ), array( 'P10', 'P11' ) ),
		'FINANCE_NO_ASSETS'        => array( __( 'No finances to divide', 'exfiles' ), array( 'P14' ) ),
		'FINANCE_GUIDANCE'         => array( __( 'No discussions / needs guidance', 'exfiles' ), array( 'P15' ) ),
		'FINANCE_FAIRNESS'         => array( __( 'Agreement but unsure', 'exfiles' ), array( 'P16' ) ),
		'FINANCE_BINDING'          => array( __( 'Agreement wants legally binding', 'exfiles' ), array( 'P17' ) ),
		'UNMARRIED_FINANCES'       => array( __( 'Unmarried + financial guidance', 'exfiles' ), array( 'P18' ) ),
		'CTA'                      => array( __( 'Every completed guide', 'exfiles' ), array( 'P19' ) ),
	);
}

/**
 * Guide paragraphs P01–P19 from the precedent document. Body is HTML.
 */
function exf_guide_default_paragraphs() {
	return array(
		'P01' => array(
			'Can you get divorced in England & Wales?',
			'<p>You can generally apply for a divorce in England &amp; Wales if:</p><ul><li>you have been married for more than one year;</li><li>your marriage has permanently broken down; and</li><li>your marriage is legally recognised in the UK.</li></ul><p>There must also be a sufficient legal connection with England &amp; Wales for the courts here to have jurisdiction. The jurisdiction rules can be technical, particularly where either spouse has lived or been domiciled outside England &amp; Wales. If your circumstances are international, you should obtain specific legal advice rather than relying solely on this guide.</p>',
		),
		'P02' => array(
			'The jurisdiction questions',
			'<p>For the purposes of this guide, the relevant jurisdiction connections include:</p><ul><li>both spouses being habitually resident in England &amp; Wales;</li><li>England &amp; Wales being the spouses’ last shared habitual residence with one spouse continuing to live here;</li><li>the respondent being habitually resident here;</li><li>the applicant having been habitually resident here for at least one year immediately before applying;</li><li>the applicant being domiciled and habitually resident here for at least six months immediately before applying; both spouses being domiciled here; or</li><li>either spouse being domiciled here.</li></ul><p>These are legal tests and the precise facts matter. If you are unsure which route applies, obtain legal advice before starting proceedings.</p>',
		),
		'P03' => array(
			'If you cannot confirm eligibility in England & Wales',
			'<p>The Ex Files’ Divorce Guide is intended for people who are eligible to apply for a divorce in England &amp; Wales. If you are not eligible to apply here, but may be eligible to divorce in another jurisdiction, you should seek advice from a lawyer qualified in that jurisdiction.</p>',
		),
		'P04' => array(
			'If you have not started your divorce',
			'<p>If you have not yet started your divorce, you can either apply yourself or ask a solicitor to deal with the divorce application for you. If you apply yourself using the GOV.UK online service, you will generally only need to pay the court fee (unless you qualify for help with fees). A solicitor will charge their own legal fees in addition to the court fee. Divorce and financial arrangements are separate processes, but can be managed concurrently: you can deal with the divorce application while also resolving the financial claims arising from the marriage.</p>',
		),
		'P05' => array(
			'The divorce process at a glance',
			'<p>Under the current divorce procedure, the process broadly involves:</p><ol><li>making a sole or joint application;</li><li>the application being issued by the court;</li><li>a 20-week period before an application can be made for the Conditional Order;</li><li>the Conditional Order being granted;</li><li>waiting at least 43 days before applying for the Final Order; and</li><li>the Final Order ending the marriage.</li></ol><p>The precise steps can differ depending on whether the application is sole or joint and whether the other spouse responds.</p>',
		),
		'P06' => array(
			'Sole or joint application',
			'<p>A sole application is made by one spouse. A joint application is made together by both spouses. A joint application can be appropriate where both people agree that they want a divorce and can engage with the process. If a joint application stops progressing because the other applicant does not complete the required step, the application can be changed to a sole application in accordance with the court process. If there is any concern about domestic abuse or pressure to make a joint application, obtain independent legal advice.</p>',
		),
		'P07' => array(
			'Your sole application has been issued but your ex has not responded',
			'<p>Your spouse should normally respond to the divorce application by completing the acknowledgement of service within 14 days of receipt. If they do not respond, this does not necessarily mean that the divorce has to stop. The court has procedures dealing with situations where service or acknowledgement is problematic, including applications concerning deemed service or alternative/dispensed service in appropriate cases. The correct route depends on what has happened and what evidence of service is available. If your spouse is not responding, check the instructions in your court account and consider obtaining legal advice before making an application concerning service.</p>',
		),
		'P08' => array(
			'The 20-week period and the Conditional Order',
			'<p>Once the divorce application has been issued, you normally need to wait 20 weeks before you can apply for the Conditional Order. The Conditional Order is the court’s confirmation that, at that stage, it sees no reason why the divorce cannot proceed. The Conditional Order does not end the marriage: you remain married until the Final Order is made. After the Conditional Order, there is a further minimum period of 43 days before the Final Order can be applied for.</p>',
		),
		'P09' => array(
			'You have received the Conditional Order',
			'<p>You have reached the Conditional Order stage. The Final Order can generally be applied for once at least 43 days have passed from the date of the Conditional Order. If you are the sole applicant, you can apply yourself; if you started with a joint application, either party can ultimately proceed as a sole applicant where the procedure permits. If you are dealing with finances, however, do not assume that obtaining the Final Order is simply an administrative step with no financial implications.</p>',
		),
		'P10' => array(
			'Divorce does not, by itself, settle the finances',
			'<p>Ending the marriage does not automatically end the financial claims that arise from the marriage. A divorce and a financial settlement are separate matters. If you and your spouse reach agreement about money and property, you will normally need a financial consent order approved by the court if you want the agreement to become legally binding. Where financial claims have not been dealt with, there can be circumstances in which claims remain available after the divorce has been finalised.</p>',
		),
		'P11' => array(
			'Think carefully before applying for the Final Order',
			'<p>If your financial arrangements have not been resolved, you should consider taking legal advice before applying for the Final Order. The timing of the Final Order can have financial consequences, including in relation to certain pension and death-related benefits. The safest course will depend on your circumstances, including whether there is an agreed or proposed financial order, the nature of the assets and pensions involved and whether either party may be affected by the loss of benefits connected with being married.</p>',
		),
		'P12' => array(
			'Moving from a joint application to a sole application',
			'<p>A joint divorce application does not necessarily have to remain joint all the way through. If the other applicant does not complete the required stage of the process, there is a mechanism for the application to proceed as a sole application. The precise step and timing depend on the stage your case has reached. If you are considering changing the application because communication with your spouse has broken down, check the current court instructions or obtain legal advice.</p>',
		),
		'P13' => array(
			'The ‘remarriage trap’',
			'<p>There is a particular issue sometimes referred to as the ‘remarriage trap’. In broad terms, where a person remarries before their financial claims arising from the first marriage have been properly resolved, there can be restrictions on certain financial applications relating to the first marriage. The rules are technical and depend on the circumstances. If your finances have not been dealt with and you are considering remarriage, obtain specific legal advice before taking steps that could affect your position.</p>',
		),
		'P14' => array(
			'You do not have any finances to divide',
			'<p>Even if you and your ex currently have no assets to divide, it is important to understand that the financial claims arising from a marriage do not necessarily disappear simply because there is nothing of value at the moment. Unless the relevant claims are properly dealt with, there may be circumstances in which claims remain available in the future, particularly if either person’s financial circumstances improve. Depending on the circumstances, claims can also relate to income rather than capital. Our Know Your Position service can help you understand the financial position before you decide how you want to proceed.</p>',
		),
		'P15' => array(
			'You have not yet agreed your finances',
			'<p>There are several ways to approach financial discussions. You may be able to reach an agreement directly with your ex, use mediation or another form of dispute resolution, or negotiate through solicitors. The appropriate route depends on the level of agreement, the complexity of the finances and the relationship between you. Before starting negotiations, it is important to understand your own financial position and what you may reasonably need or be entitled to. Our Know Your Position service is designed to give you a clearer starting point.</p>',
		),
		'P16' => array(
			'You have agreed a deal but are unsure whether it is fair',
			'<p>An agreement between you and your ex is not, by itself, the same thing as a court-approved financial settlement. If you want an agreement about the division of matrimonial finances to become legally binding, it will generally need to be recorded in a financial consent order and approved by the court. The judge is not required simply to rubber-stamp an agreement: the court considers the proposed order and can ask for changes if it is not satisfied that the proposed outcome is appropriate in all the circumstances. If you have reached an agreement but want reassurance about whether it is sensible before taking the next step, our Check Your Deal service can provide further guidance.</p>',
		),
		'P17' => array(
			'You have agreed a deal and want to make it legally binding',
			'<p>If you and your ex have reached agreement about your finances, you can ask the court to approve the agreement by way of a financial consent order. The consent order is the document that records the agreed financial arrangements and, once approved and effective, makes them legally binding. The court will consider whether the proposed order is fair in the circumstances and may ask for changes or further information. Our Make It Legal service is designed for people who have reached an agreement and want assistance with preparing the paperwork for court approval.</p>',
		),
		'P18' => array(
			'You are not married but want financial guidance',
			'<p>If you were not married or in a civil partnership, the financial claims available to you are different from those available to spouses. Unmarried couples can nevertheless have potential claims relating to property, beneficial ownership, contributions, children and other matters depending on their circumstances. The legal position is fact-specific. If you are separating from an unmarried partner and want to understand your financial position, our Know Your Position service may be a useful starting point.</p>',
		),
		'P19' => array(
			'Next steps with The Ex Files',
			'<p>If you would like more tailored guidance, The Ex Files offers fixed-fee services designed to help you take the next step without committing to an open-ended solicitor retainer. Know Your Position is intended to help you understand your financial position. Check Your Deal is intended to help you sense-check an agreement you have already reached. Make It Legal is intended to assist with preparing a financial consent order where you have reached an agreement and want it put before the court for approval.</p>',
		),
	);
}

/**
 * Tooltip copy (the sheet's "Tooltip Copy" tab).
 */
function exf_guide_default_tooltips() {
	return array(
		array( 'TIP1', 'Habitual residence', 'In simple terms, habitual residence is usually about where your life is genuinely based. It is not necessarily the same as your nationality, the address on your passport or the place where you happen to be staying temporarily. If your circumstances involve living in more than one country, get legal advice before relying on this questionnaire.' ),
		array( 'TIP2', 'Domicile', 'Domicile is a legal concept concerning the country/nation that is your permanent home, or the place with which you have the closest permanent connection. You can have only one domicile at a time. Domicile can be legally complex, particularly where you have lived in different countries or have moved with an intention to settle elsewhere.' ),
		array( 'TIP3', 'Legally recognised marriage', 'A marriage must be recognised as legally valid for the purposes of divorce in England & Wales. This can raise issues where a marriage took place overseas, where there are questions about the formalities of the marriage, or where more than one legal system is involved. If your marriage took place outside the UK, check the position carefully before relying on this guide.' ),
	);
}

/**
 * Jurisdiction routes (the sheet's "Jurisdiction Routes" tab): question, explanation.
 */
function exf_guide_default_routes() {
	return array(
		array( 'Are you and your spouse both habitually resident in England & Wales?', 'Habitual residence is broadly about where your life is ordinarily based. It is more than simply being present somewhere temporarily.' ),
		array( 'Were you and your spouse last habitually resident in England & Wales, and does one of you still live there?', 'This route can apply where England & Wales was your last shared habitual residence and one of you continues to live here.' ),
		array( 'Is your spouse habitually resident in England & Wales?', 'This route may apply where the respondent/spouse is habitually resident here.' ),
		array( 'Have you been habitually resident in England & Wales for at least one year immediately before applying?', 'This route may apply where you, as the applicant, have been habitually resident here for the required one-year period.' ),
		array( 'Are you domiciled and habitually resident in England & Wales, and have you lived here for at least six months immediately before applying?', 'Domicile is a legal concept concerned with the country/nation you regard as your permanent home, taking account of the relevant facts and intentions. It is not simply the same as your current address.' ),
		array( 'Are you and your spouse both domiciled in England & Wales?', 'Domicile is a legal concept and should not be reduced to ‘where you live’. If you are unsure, seek legal advice.' ),
		array( 'Is either you or your spouse domiciled in England & Wales?', 'There are circumstances in which the court has jurisdiction where either party is domiciled in England & Wales. The precise circumstances should be checked against the current application rules.' ),
	);
}

/**
 * Other editable copy and its defaults.
 */
function exf_guide_default_copy() {
	return array(
		'exit_message'       => 'The Ex Files’ divorce guide is designed for people going through, or contemplating, separation/divorce.',
		'q3a_text'           => '<p>Based on your answer, we cannot confirm that you are eligible to apply for a divorce in England &amp; Wales.</p><p>If you are not eligible to divorce here, a lawyer qualified in the country you are connected with can advise you on divorcing there. Your guide will still explain this, and we may be able to help you understand your financial position.</p>',
		'pdf_title'          => 'Your guide to divorce',
		'pdf_point'          => 'To explain the divorce process and the financial steps relevant to the answers provided in the online questionnaire. You can find the answers you provided to the questionnaire at Annex A.',
		'pdf_important'      => 'This guide gives general information based on the answers supplied. It does not constitute legal advice and should be treated as guidance rather than a substitute. The Ex Files has not reviewed financial disclosure or independently verified the answers.',
		'pdf_footer'         => 'The Ex Files • Making separation less alien',
		'email_subject'      => 'Your free divorce guide from The Ex Files',
		'email_body'         => "Hello,\n\nThank you for using The Ex Files’ free divorce guide. Your personalised guide is attached as a PDF. Your answers to the questionnaire are included at Annex A.\n\nThis guide is general information based on your answers. It is not legal advice.\n\n{services}Best wishes,\nThe Ex Files",
		'email_services'     => "You told us you’d like to hear about these services:\n{list}\n\n",
		'marketing_label'    => 'I’d like The Ex Files to email me about their services. Reply to any of our emails to stop.',
		'privacy_note'       => 'We only use your email address to send you this guide, keep a record of what we sent and, if you tick the box below, to tell you about our services. We don’t ask for your name or your ex’s details.',
	);
}
