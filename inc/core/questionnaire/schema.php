<?php
/**
 * The full service questionnaire, defined in code from the client's spreadsheet
 * ("The Ex Files Full Service Questionnaire.xlsx"). Dropdown options are copied
 * from the spreadsheet's lists.
 *
 * Field definition keys:
 *   type      text|textarea|date|select|radio|number|money|repeater|group|outgoings
 *   label     Question.
 *   options   Choices (select/radio).
 *   required  Must be answered (only when visible).
 *   show_if   [ field key in the same item/section, [ values ] ].
 *   help      Hint under the field.
 *   fields    Sub-fields (repeater, group).
 *   item      Repeater item name ("property").
 *   freq      Money: ask how often (true, or a list of frequencies).
 *   gross_net Money: ask whether gross or net.
 *
 * @package exfiles
 */

defined( 'ABSPATH' ) || exit;

const EXF_Q_YND = array( 'Yes', 'No', "Don't know" );

/**
 * Frequencies and how many times a year each happens.
 */
function exf_q_frequencies() {
	return array(
		'Weekly'      => 52,
		'Fortnightly' => 26,
		'Four-weekly' => 13,
		'Monthly'     => 12,
		'Termly'      => 3,
		'Quarterly'   => 4,
		'Annual'      => 1,
		'Other'       => 0,
		"Don't know"  => 0,
	);
}

/**
 * Monthly equivalent of an amount, or null if it can't be worked out.
 *
 * @param float|string $amount    Amount.
 * @param string       $frequency Frequency.
 */
function exf_q_monthly( $amount, $frequency ) {
	$per_year = exf_q_frequencies()[ $frequency ] ?? 0;
	if ( '' === $amount || null === $amount || ! is_numeric( $amount ) || ! $per_year ) {
		return null;
	}
	return round( (float) $amount * $per_year / 12, 2 );
}

/**
 * A money field (certainty, amount, as-at date, basis of estimate).
 *
 * @param string $label Label.
 * @param array  $extra Extra keys.
 */
function exf_q_money( $label, array $extra = array() ) {
	return array(
		'type'  => 'money',
		'label' => $label,
	) + $extra;
}

/**
 * Section-level "Additional information".
 */
function exf_q_additional() {
	return array(
		'type'  => 'textarea',
		'label' => __( 'Additional information', 'exfiles' ),
	);
}

/**
 * The outgoings categories from the spreadsheet.
 */
function exf_q_outgoing_categories() {
	return array(
		'Housing: rent or mortgage',
		'Council tax and service charge / ground rent',
		'Gas, electricity, water and other utilities',
		'Home and contents insurance; buildings insurance',
		'Food, groceries and household supplies',
		'Clothing, footwear, haircuts and personal care',
		'Car finance, fuel, insurance, tax, MOT, servicing and parking',
		'Public transport, taxis and commuting',
		'Childcare, nursery, school fees and school meals',
		'Children’s activities, clothing, healthcare and child maintenance paid',
		'Medical, dental, prescriptions, therapy and disability costs',
		'Phone, broadband, TV licence and subscriptions',
		'Debt repayments (identify debts also listed in Liabilities)',
		'Pension contributions and savings',
		'Pets: food, veterinary care and insurance',
		'Leisure, holidays, gifts and social spending',
		'Professional fees, work expenses and tax not withheld',
	);
}

/**
 * Income fields for one person.
 *
 * @param bool $spouse For the spouse/partner.
 */
function exf_q_income_fields( $spouse ) {
	$who = $spouse ? __( 'their', 'exfiles' ) : __( 'your', 'exfiles' );
	return array(
		'employment'  => array(
			'type'   => 'repeater',
			'label'  => __( 'Employment', 'exfiles' ),
			'item'   => __( 'job', 'exfiles' ),
			'fields' => array(
				'occupation' => array(
					'type'  => 'text',
					'label' => __( 'Occupation', 'exfiles' ),
				),
				'gross'      => exf_q_money( __( 'Gross salary (before tax)', 'exfiles' ), array( 'freq' => true ) ),
				'net'        => exf_q_money( __( 'Net salary (take-home pay)', 'exfiles' ), array( 'freq' => true ) ),
			),
		),
		'dividends'   => array(
			'type'   => 'repeater',
			/* translators: %s: your / their */
			'label'  => sprintf( __( 'Dividends from %s business interests', 'exfiles' ), $who ),
			'item'   => __( 'dividend', 'exfiles' ),
			'fields' => array(
				'business' => array(
					'type'  => 'text',
					'label' => __( 'Business', 'exfiles' ),
				),
				'amount'   => exf_q_money( __( 'Amount', 'exfiles' ), array( 'freq' => true ) ),
			),
		),
		'benefits'    => array(
			'type'   => 'repeater',
			'label'  => __( 'Benefits', 'exfiles' ),
			'item'   => __( 'benefit', 'exfiles' ),
			'fields' => array(
				'benefit' => array(
					'type'  => 'text',
					'label' => __( 'Benefit', 'exfiles' ),
				),
				'amount'  => exf_q_money( __( 'Amount', 'exfiles' ), array( 'freq' => true ) ),
			),
		),
		'maintenance' => exf_q_money( __( 'Child maintenance received', 'exfiles' ), array( 'freq' => true, 'none' => true ) ),
		'pension_inc' => array(
			'type'   => 'repeater',
			'label'  => __( 'Pension income currently received', 'exfiles' ),
			'item'   => __( 'pension income', 'exfiles' ),
			'fields' => array(
				'kind'   => array(
					'type'    => 'select',
					'label'   => __( 'Type', 'exfiles' ),
					'options' => array( 'State pension', 'Private pension' ),
				),
				'amount' => exf_q_money( __( 'Amount', 'exfiles' ), array( 'freq' => true ) ),
			),
		),
		'other_inc'   => array(
			'type'   => 'repeater',
			'label'  => __( 'Other income', 'exfiles' ),
			'item'   => __( 'income', 'exfiles' ),
			'fields' => array(
				'source' => array(
					'type'  => 'text',
					'label' => __( 'Source', 'exfiles' ),
				),
				'amount' => exf_q_money( __( 'Amount', 'exfiles' ), array( 'freq' => true ) ),
			),
		),
	);
}

/**
 * All sections, in order.
 *
 * Section keys: title, intro, gate (question or ''), gate_options, fields, money_note, show (callable on all data).
 */
function exf_q_sections() {
	$owner_general = array( 'Me', 'My spouse/partner', 'Both of us', 'Someone else', "Don't know" );
	$money_note    = __( "For every amount, choose Known current, Estimated current or Don't know. An estimate must be labelled; never guess. For known figures, use the latest statement or valuation and give its date.", 'exfiles' );

	$sections = array(
		'about'       => array(
			'title'  => __( 'About you', 'exfiles' ),
			'intro'  => __( 'Basic relationship and family questions.', 'exfiles' ),
			'fields' => array(
				'married'            => array(
					'type'     => 'radio',
					'label'    => __( 'Are you married to (or in a civil partnership with) the person you are separating from?', 'exfiles' ),
					'options'  => array( 'Yes', 'No' ),
					'required' => true,
				),
				'relationship_start' => array(
					'type'  => 'date',
					'label' => __( 'Date your relationship started', 'exfiles' ),
				),
				'cohabitation_start' => array(
					'type'  => 'date',
					'label' => __( 'Date you started living together', 'exfiles' ),
				),
				'marriage_date'      => array(
					'type'    => 'date',
					'label'   => __( 'Date of marriage', 'exfiles' ),
					'show_if' => array( 'married', array( 'Yes' ) ),
				),
				'marriage_place'     => array(
					'type'    => 'text',
					'label'   => __( 'Place of marriage', 'exfiles' ),
					'show_if' => array( 'married', array( 'Yes' ) ),
				),
				'separation_date'    => array(
					'type'  => 'date',
					'label' => __( 'Date of separation', 'exfiles' ),
				),
				'agreement_reached'  => array(
					'type'     => 'radio',
					'label'    => __( 'Have you reached any financial agreement?', 'exfiles' ),
					'options'  => EXF_Q_YND,
					'required' => true,
				),
				'offer_received'     => array(
					'type'     => 'radio',
					'label'    => __( 'Have you received a financial offer?', 'exfiles' ),
					'options'  => EXF_Q_YND,
					'required' => true,
				),
				'offer_to_make'      => array(
					'type'     => 'radio',
					'label'    => __( 'Would you like to make a financial offer?', 'exfiles' ),
					'options'  => array( 'Yes', 'No', 'Not sure' ),
					'required' => true,
				),
				'additional'         => exf_q_additional(),
			),
		),
		'children'    => array(
			'title'  => __( 'Children', 'exfiles' ),
			'intro'  => __( 'Complete one block per child, including a stepchild or a child treated as part of your family.', 'exfiles' ),
			'gate'   => __( 'Do you or your partner have any children?', 'exfiles' ),
			'fields' => array(
				'children'   => array(
					'type'   => 'repeater',
					'label'  => __( 'Children', 'exfiles' ),
					'item'   => __( 'child', 'exfiles' ),
					'fields' => array(
						'name'            => array(
							'type'     => 'text',
							'label'    => __( "Child's name", 'exfiles' ),
							'optional' => true, // The only question the client's spreadsheet marks optional.
						),
						'gender'          => array(
							'type'    => 'select',
							'label'   => __( 'Gender', 'exfiles' ),
							'options' => array( 'Female', 'Male', 'Other / prefer to self-describe', 'Prefer not to say' ),
						),
						'dob'             => array(
							'type'  => 'date',
							'label' => __( 'Date of birth', 'exfiles' ),
						),
						'education'       => array(
							'type'    => 'select',
							'label'   => __( 'Education', 'exfiles' ),
							'options' => array( 'Compulsory School Age (State-funded)', 'Compulsory School Age (Privately-funded)', 'Other', "Don't know" ),
						),
						'education_other' => array(
							'type'    => 'text',
							'label'   => __( 'Please tell us more about their education', 'exfiles' ),
							'show_if' => array( 'education', array( 'Other', "Don't know" ) ),
						),
						'school_fees'     => exf_q_money(
							__( 'School fees', 'exfiles' ),
							array(
								'freq'    => array( 'Monthly', 'Termly', 'Annual', 'Other', "Don't know" ),
								'show_if' => array( 'education', array( 'Compulsory School Age (Privately-funded)' ) ),
							)
						),
						'residence'       => array(
							'type'    => 'select',
							'label'   => __( 'Where does this child usually live?', 'exfiles' ),
							'options' => array( 'With me', 'With the other parent', 'Independently', 'Somewhere else' ),
						),
						'parent'          => array(
							'type'    => 'select',
							'label'   => __( "Who is the child's parent?", 'exfiles' ),
							'options' => array( 'Me', 'My spouse/partner', 'Both of us' ),
						),
						'child_of_family' => array(
							'type'    => 'radio',
							'label'   => __( 'Has the other party treated them as a child of the family?', 'exfiles' ),
							'options' => array( 'Yes', 'No' ),
							'show_if' => array( 'parent', array( 'Me', 'My spouse/partner' ) ),
						),
						'care'            => array(
							'type'    => 'textarea',
							'label'   => __( 'Please explain the relationship and care arrangements', 'exfiles' ),
							'show_if' => array( 'child_of_family', array( 'Yes' ) ),
						),
						'additional'      => exf_q_additional(),
					),
				),
				'additional' => exf_q_additional(),
			),
		),
		'properties'  => array(
			'title'      => __( 'Properties', 'exfiles' ),
			'intro'      => __( 'Only complete if you or your spouse/partner own, or may own, property.', 'exfiles' ),
			'gate'       => __( 'Do you or your spouse/partner own any property?', 'exfiles' ),
			'money_note' => $money_note,
			'fields'     => array(
				'properties' => array(
					'type'   => 'repeater',
					'label'  => __( 'Properties', 'exfiles' ),
					'item'   => __( 'property', 'exfiles' ),
					'fields' => array(
						'address'    => array(
							'type'  => 'textarea',
							'label' => __( 'Property address', 'exfiles' ),
							'rows'  => 3,
						),
						'owner'      => array(
							'type'    => 'select',
							'label'   => __( 'Who owns it?', 'exfiles' ),
							'options' => $owner_general,
						),
						'title'      => array(
							'type'  => 'text',
							'label' => __( 'Names of owners on the legal title', 'exfiles' ),
						),
						'home'       => array(
							'type'    => 'radio',
							'label'   => __( 'Is this the family home?', 'exfiles' ),
							'options' => array( 'Yes', 'No' ),
						),
						'value'      => exf_q_money( __( 'Current value', 'exfiles' ) ),
						'mortgage'   => exf_q_money( __( 'Outstanding mortgage', 'exfiles' ) ),
						'erc'        => exf_q_money( __( 'Early repayment charge or other penalty', 'exfiles' ) ),
						'cgt'        => exf_q_money( __( 'Capital gains tax that would arise', 'exfiles' ) ),
						'additional' => exf_q_additional(),
					),
				),
				'additional' => exf_q_additional(),
			),
		),
		'accounts'    => array(
			'title'      => __( 'Bank accounts', 'exfiles' ),
			'intro'      => __( 'Only complete if you or your spouse/partner have bank, savings or cash accounts.', 'exfiles' ),
			'gate'       => __( 'Do you or your spouse/partner have any bank or savings accounts?', 'exfiles' ),
			'money_note' => $money_note,
			'fields'     => array(
				'accounts'   => array(
					'type'   => 'repeater',
					'label'  => __( 'Accounts', 'exfiles' ),
					'item'   => __( 'account', 'exfiles' ),
					'fields' => array(
						'bank'       => array(
							'type'  => 'text',
							'label' => __( 'Bank or building society', 'exfiles' ),
						),
						'kind'       => array(
							'type'    => 'select',
							'label'   => __( 'Account type', 'exfiles' ),
							'options' => array( 'Current', 'Savings', 'ISA', 'Other' ),
						),
						'owner'      => array(
							'type'    => 'select',
							'label'   => __( 'Who owns the account?', 'exfiles' ),
							'options' => array( 'Me', 'My spouse/partner', 'Me and my spouse/partner', 'Me and somebody else', "Don't know" ),
						),
						'balance'    => exf_q_money( __( 'Current balance', 'exfiles' ) ),
						'additional' => exf_q_additional(),
					),
				),
				'additional' => exf_q_additional(),
			),
		),
		'investments' => array(
			'title'      => __( 'Investments', 'exfiles' ),
			'intro'      => __( 'Only complete if you or your spouse/partner hold investments, such as shares, funds or investment accounts.', 'exfiles' ),
			'gate'       => __( 'Do you or your spouse/partner have any investments?', 'exfiles' ),
			'money_note' => $money_note,
			'fields'     => array(
				'investments' => array(
					'type'   => 'repeater',
					'label'  => __( 'Investments', 'exfiles' ),
					'item'   => __( 'investment', 'exfiles' ),
					'fields' => array(
						'description'   => array(
							'type'  => 'text',
							'label' => __( 'Investment type or description', 'exfiles' ),
						),
						'owner'         => array(
							'type'    => 'select',
							'label'   => __( 'Who owns the investment?', 'exfiles' ),
							'options' => $owner_general,
						),
						'value'         => exf_q_money( __( 'Current realisable value', 'exfiles' ) ),
						'maturity'      => exf_q_money( __( 'Future or maturity value', 'exfiles' ) ),
						'maturity_date' => array(
							'type'  => 'date',
							'label' => __( 'Expected maturity date, if known', 'exfiles' ),
							'optional' => true, // Spreadsheet: "expected date if known".
						),
						'additional'    => exf_q_additional(),
					),
				),
				'additional'  => exf_q_additional(),
			),
		),
		'assets'      => array(
			'title'      => __( 'Other assets', 'exfiles' ),
			'intro'      => __( 'Only complete if you or your spouse/partner have other assets with a resale value over £500, such as vehicles, artwork or jewellery.', 'exfiles' ),
			'gate'       => __( 'Do you or your spouse/partner have other assets worth over £500?', 'exfiles' ),
			'money_note' => $money_note,
			'fields'     => array(
				'assets'     => array(
					'type'   => 'repeater',
					'label'  => __( 'Assets', 'exfiles' ),
					'item'   => __( 'asset', 'exfiles' ),
					'fields' => array(
						'description' => array(
							'type'  => 'text',
							'label' => __( 'Asset description', 'exfiles' ),
						),
						'owner'       => array(
							'type'    => 'select',
							'label'   => __( 'Who owns the asset?', 'exfiles' ),
							'options' => $owner_general,
						),
						'value'       => exf_q_money( __( 'Resale or current value', 'exfiles' ) ),
						'additional'  => exf_q_additional(),
					),
				),
				'additional' => exf_q_additional(),
			),
		),
		'business'    => array(
			'title'      => __( 'Business interests', 'exfiles' ),
			'intro'      => __( 'Only complete if you or your spouse/partner have a business interest.', 'exfiles' ),
			'gate'       => __( 'Do you or your spouse/partner have any business interests?', 'exfiles' ),
			'money_note' => $money_note,
			'fields'     => array(
				'businesses' => array(
					'type'   => 'repeater',
					'label'  => __( 'Business interests', 'exfiles' ),
					'item'   => __( 'business', 'exfiles' ),
					'fields' => array(
						'name'            => array(
							'type'  => 'text',
							'label' => __( 'Business or trading name', 'exfiles' ),
						),
						'owner'           => array(
							'type'    => 'select',
							'label'   => __( 'Who owns the interest?', 'exfiles' ),
							'options' => $owner_general,
						),
						'structure'       => array(
							'type'    => 'select',
							'label'   => __( 'Business structure', 'exfiles' ),
							'help'    => __( 'The legal structure of the business, not the person’s job title.', 'exfiles' ),
							'options' => array( 'Sole trader', 'Ordinary partnership', 'Limited liability partnership (LLP)', 'Limited company', 'Other', "Don't know" ),
						),
						'structure_other' => array(
							'type'    => 'text',
							'label'   => __( 'Please describe the structure', 'exfiles' ),
							'show_if' => array( 'structure', array( 'Other' ) ),
						),
						'percent'         => array(
							'type'  => 'number',
							'label' => __( 'Shareholding or ownership (%)', 'exfiles' ),
							'attrs' => ' min="0" max="100" step="0.01"',
						),
						'know_value'      => array(
							'type'    => 'radio',
							'label'   => __( 'Do you know the value of the interest in this business?', 'exfiles' ),
							'options' => EXF_Q_YND,
						),
						'value'           => exf_q_money( __( 'Value of the interest', 'exfiles' ), array( 'show_if' => array( 'know_value', array( 'Yes' ) ) ) ),
						'value_source'    => array(
							'type'    => 'text',
							'label'   => __( 'Where did you get this value from?', 'exfiles' ),
							'show_if' => array( 'know_value', array( 'Yes' ) ),
						),
						'cgt'             => exf_q_money( __( 'Capital gains tax that would arise', 'exfiles' ) ),
						'additional'      => exf_q_additional(),
					),
				),
				'additional' => exf_q_additional(),
			),
		),
		'liabilities' => array(
			'title'      => __( 'Liabilities', 'exfiles' ),
			'intro'      => __( 'Only complete if you or your spouse/partner have debts or other liabilities: loans, credit cards, tax, guarantees and so on.', 'exfiles' ),
			'gate'       => __( 'Do you or your spouse/partner have any debts or liabilities?', 'exfiles' ),
			'money_note' => $money_note,
			'fields'     => array(
				'liabilities' => array(
					'type'   => 'repeater',
					'label'  => __( 'Liabilities', 'exfiles' ),
					'item'   => __( 'liability', 'exfiles' ),
					'fields' => array(
						'creditor'      => array(
							'type'  => 'text',
							'label' => __( 'Creditor or type of liability', 'exfiles' ),
						),
						'liable'        => array(
							'type'    => 'select',
							'label'   => __( 'Who is liable?', 'exfiles' ),
							'help'    => __( 'Usually the person or people who applied for or signed the credit agreement.', 'exfiles' ),
							'options' => array( 'Me', 'My spouse/partner', 'Both of us', 'Me and somebody else', 'My spouse/partner and somebody else', "Don't know" ),
						),
						'names'         => array(
							'type'  => 'text',
							'label' => __( 'Names on the agreement', 'exfiles' ),
						),
						'supplementary' => array(
							'type'    => 'radio',
							'label'   => __( 'Is anyone only a supplementary (second) cardholder?', 'exfiles' ),
							'help'    => __( 'A second cardholder on a credit card is not necessarily a joint borrower.', 'exfiles' ),
							'options' => EXF_Q_YND,
						),
						'balance'       => exf_q_money( __( 'Outstanding balance', 'exfiles' ) ),
						'monthly'       => exf_q_money( __( 'Monthly repayment', 'exfiles' ) ),
						'additional'    => exf_q_additional(),
					),
				),
				'additional'  => exf_q_additional(),
			),
		),
		'pensions'    => array(
			'title'      => __( 'Pensions', 'exfiles' ),
			'intro'      => __( 'Only complete if you or your spouse/partner have pensions. The State Pension may need a forecast rather than a CETV.', 'exfiles' ),
			'gate'       => __( 'Do you or your spouse/partner have any pensions?', 'exfiles' ),
			'money_note' => $money_note,
			'fields'     => array(
				'pensions'   => array(
					'type'   => 'repeater',
					'label'  => __( 'Pensions', 'exfiles' ),
					'item'   => __( 'pension', 'exfiles' ),
					'fields' => array(
						'provider'   => array(
							'type'  => 'text',
							'label' => __( 'Pension provider', 'exfiles' ),
						),
						'owner'      => array(
							'type'    => 'select',
							'label'   => __( 'Whose pension is it?', 'exfiles' ),
							'options' => array( 'Me', 'My spouse/partner' ),
						),
						'kind'       => array(
							'type'    => 'select',
							'label'   => __( 'Pension type', 'exfiles' ),
							'help'    => __( "If you don't know the scheme type, choose Don't know.", 'exfiles' ),
							'options' => array( 'Defined contribution', 'Defined benefit / final salary', 'Additional voluntary contribution (AVC)', 'Personal pension / SIPP', 'Other', "Don't know" ),
						),
						'in_payment' => array(
							'type'    => 'radio',
							'label'   => __( 'Is it in payment or drawdown?', 'exfiles' ),
							'options' => array( 'Yes', 'No', 'Partially', "Don't know" ),
						),
						'know_cetv'  => array(
							'type'    => 'radio',
							'label'   => __( 'Do you know the cash equivalent transfer value (CETV)?', 'exfiles' ),
							'options' => EXF_Q_YND,
						),
						'cetv'       => exf_q_money( __( 'CETV', 'exfiles' ), array( 'show_if' => array( 'know_cetv', array( 'Yes' ) ) ) ),
						'additional' => exf_q_additional(),
					),
				),
				'additional' => exf_q_additional(),
			),
		),
		'income'      => array(
			'title'      => __( 'Income', 'exfiles' ),
			'intro'      => __( 'Enter your income and your spouse/partner’s separately. For each amount, give how often it is paid; we work out the monthly equivalent.', 'exfiles' ),
			'money_note' => $money_note,
			'fields'     => array(
				'client_has' => array(
					'type'     => 'radio',
					'label'    => __( 'Do you have any income?', 'exfiles' ),
					'options'  => EXF_Q_YND,
					'required' => true,
				),
				'client'     => array(
					'type'    => 'group',
					'label'   => __( 'Your income', 'exfiles' ),
					'show_if' => array( 'client_has', array( 'Yes' ) ),
					'fields'  => exf_q_income_fields( false ),
				),
				'spouse_has' => array(
					'type'     => 'radio',
					'label'    => __( 'Does your spouse/partner have any income?', 'exfiles' ),
					'options'  => EXF_Q_YND,
					'required' => true,
				),
				'spouse'     => array(
					'type'    => 'group',
					'label'   => __( "Your spouse/partner's income", 'exfiles' ),
					'show_if' => array( 'spouse_has', array( 'Yes' ) ),
					'fields'  => exf_q_income_fields( true ),
				),
				'additional' => exf_q_additional(),
			),
		),
		'outgoings'   => array(
			'title'  => __( 'Monthly outgoings', 'exfiles' ),
			'intro'  => __( 'Your regular spending now, and what you expect it to be after separating. Enter each amount as you pay it (for example a quarterly bill as the quarterly amount) and we work out the monthly equivalent. Leave a row blank if it doesn’t apply.', 'exfiles' ),
			'fields' => array(
				'rows'       => array(
					'type'  => 'outgoings',
					'label' => __( 'Outgoings', 'exfiles' ),
				),
				'other'      => array(
					'type'   => 'repeater',
					'label'  => __( 'Other regular spending', 'exfiles' ),
					'item'   => __( 'other spending', 'exfiles' ),
					'fields' => array(
						'description' => array(
							'type'  => 'text',
							'label' => __( 'What is it?', 'exfiles' ),
						),
						'current'     => exf_q_money( __( 'Current amount', 'exfiles' ), array( 'freq' => true ) ),
						'future'      => exf_q_money( __( 'Future amount', 'exfiles' ), array( 'freq' => true ) ),
					),
				),
				'additional' => array(
					'type'  => 'textarea',
					'label' => __( 'Additional information, one-off costs or expected changes', 'exfiles' ),
				),
			),
		),
		'agreements'  => array(
			'title'  => __( 'Agreements and offers', 'exfiles' ),
			'intro'  => __( 'You told us about an agreement or offer. Please give the details.', 'exfiles' ),
			'show'   => function ( $data ) {
				$a = $data['about'] ?? array();
				return 'Yes' === ( $a['agreement_reached'] ?? '' ) || 'Yes' === ( $a['offer_received'] ?? '' ) || 'Yes' === ( $a['offer_to_make'] ?? '' );
			},
			'fields' => array(
				'agreement_details' => array(
					'type'      => 'textarea',
					'label'     => __( 'Details of the agreement', 'exfiles' ),
					'help'      => __( 'Date, terms, and whether it is recorded in writing.', 'exfiles' ),
					'show_data' => array( 'about', 'agreement_reached', array( 'Yes' ) ),
				),
				'offer_received_details' => array(
					'type'      => 'textarea',
					'label'     => __( 'Details of the offer you received', 'exfiles' ),
					'help'      => __( 'Proposed division, amounts and conditions.', 'exfiles' ),
					'show_data' => array( 'about', 'offer_received', array( 'Yes' ) ),
				),
				'offer_to_make_details'  => array(
					'type'      => 'textarea',
					'label'     => __( 'Details of the offer you would like to make', 'exfiles' ),
					'help'      => __( 'Your proposal and any important conditions.', 'exfiles' ),
					'show_data' => array( 'about', 'offer_to_make', array( 'Yes' ) ),
				),
				'additional'             => exf_q_additional(),
			),
		),
		'anything'    => array(
			'title'  => __( 'Anything else', 'exfiles' ),
			'intro'  => __( 'Use this for anything relevant that we haven’t asked about.', 'exfiles' ),
			'fields' => array(
				'has_more' => array(
					'type'     => 'radio',
					'label'    => __( 'Is there anything else we should know?', 'exfiles' ),
					'options'  => array( 'Yes', 'No' ),
					'required' => true,
				),
				'details'  => array(
					'type'    => 'textarea',
					'label'   => __( 'Please tell us', 'exfiles' ),
					'show_if' => array( 'has_more', array( 'Yes' ) ),
				),
			),
		),
	);
	return apply_filters( 'exf_questionnaire_sections', exf_q_apply_required( $sections ) );
}

/**
 * The client's spreadsheet marks only "Child's name" as optional, so every other
 * question is required when it applies (hidden questions never are). Exceptions:
 * the "Additional information" boxes, fields marked 'optional' (where the
 * spreadsheet says so or says "if known"), and the outgoings table.
 *
 * @param array $sections Sections.
 * @return array
 */
function exf_q_apply_required( array $sections ) {
	foreach ( $sections as &$section ) {
		$section['fields'] = exf_q_apply_required_fields( $section['fields'] );
		// Answering Yes to a section's opening question means at least one block
		// (account, property, child…) has to be filled in.
		if ( ! empty( $section['gate'] ) ) {
			foreach ( $section['fields'] as &$field ) {
				if ( 'repeater' === $field['type'] ) {
					$field['min_items'] = 1;
				}
			}
			unset( $field );
		}
	}
	return $sections;
}

/**
 * Mark question fields required (see exf_q_apply_required()).
 *
 * @param array $fields Field definitions.
 * @return array
 */
function exf_q_apply_required_fields( array $fields ) {
	foreach ( $fields as $key => &$field ) {
		if ( ! empty( $field['fields'] ) ) {
			$field['fields'] = exf_q_apply_required_fields( $field['fields'] );
		}
		if ( in_array( $field['type'], array( 'text', 'textarea', 'date', 'select', 'radio', 'number' ), true )
			&& 'additional' !== $key && empty( $field['optional'] ) ) {
			$field['required'] = true;
		}
	}
	return $fields;
}

/**
 * Sections that apply, given the answers so far.
 *
 * @param array $data All answers.
 * @return string[] Section keys.
 */
function exf_q_visible_sections( array $data ) {
	$keys = array();
	foreach ( exf_q_sections() as $key => $section ) {
		if ( empty( $section['show'] ) || call_user_func( $section['show'], $data ) ) {
			$keys[] = $key;
		}
	}
	return $keys;
}
