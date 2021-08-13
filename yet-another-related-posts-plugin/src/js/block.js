(function (blocks, i18n, element, components, editor, blockEditor) {
	var el = element.createElement;
	var useEffect = element.useEffect;
	const { registerBlockType, createBlock } = blocks;
	const { __ } = i18n; //translation functions
	var ServerSideRender = wp.serverSideRender;
	const { RichText, InspectorControls } = blockEditor;

	const iconEl = el(
		'svg',
		{ width: '24px', height: '24px', viewBox: '0 0 145 191' },
		el(
			'g',
			{ stroke: 'none', strokeWidth: '1', fill: 'none', fillRule: 'evenodd' },
			el(
				'g',
				{
					id: 'mark',
					transform: 'translate(1.000000, 0.000000)',
					fill: '#000000',
					fillRule: 'nonzero',
				},
				[
					el(
						'g',
						{
							id: 'coffee',
							transform:
								'translate(71.500000, 120.703704) scale(-1, 1) rotate(-180.000000) translate(-71.500000, -120.703704) translate(0.000000, 51.703704)',
						},
						[
							el('path', {
								d: 'M42.8605706,136.08228 C32.8383705,134.783388 25.4669697,132.899997 20.7463681,130.432103 L17.3693225,127.801849 L17.6961333,113.286738 C17.9140072,99.3561292 17.9140072,98.6742113 15.626331,96.8232912 C9.85267229,92.1472827 0,79.2639855 0,68.5481325 C0,63.190206 1.55910743,60.0622836 4.82721619,54.4121066 C7.00595535,50.8076833 13.5249703,45.5370334 14.7232769,44.5628649 C17.0109529,43.0041953 18.4586919,44.1303138 17.6961333,33.1222102 C16.8246376,19.386435 16.0620789,17.4285621 24.0144768,12.8499703 C34.7992358,6.61529219 42.7516337,3.59536997 52.7738339,1.7444499 C76.6310277,-2.44447446 113.342783,1.25736568 126.30628,9.14813017 C133.931868,13.9215556 136.546355,16.844061 136.546355,20.9355684 C140.468085,87.8934137 142.537888,121.85942 142.755761,122.833589 C143.191509,125.074177 142.755761,125.658677 139.160842,127.314764 C134.040804,129.652769 127.831398,130.821771 106.588691,133.354609 C97.6558607,134.426194 87.0889758,135.692613 83.1672452,136.277114 C74.5612255,137.446116 52.55596,137.348699 42.8605706,136.08228 Z M84.5302734,132.078704 C90.2338167,131.384856 99.0983213,130.250086 109.038086,129.19345 C123.269589,127.025504 135.419901,125.626331 136.052413,123.330859 C136.26325,122.693228 123.934295,120.974239 120.350064,119.826503 C114.130371,117.786084 102.282696,120.185881 90.3749197,122.413563 C78.5049292,124.634175 65.4563408,123.330859 57.2545987,123.330859 C57.2545987,123.330859 52.4754466,122.523193 42.9171425,120.907861 C34.8760316,119.548931 36.2210561,119.122494 30.0013622,121.800545 C26.4171319,123.458385 23.2545757,125.243752 23.1491572,126.00891 C23.1491572,126.646541 23.4436238,128.461522 26.4171319,129.962221 C30.7587891,132.153411 43.5141602,132.441008 54.7143555,132.987395 C63.4858398,132.987395 78.8267301,132.772551 84.5302734,132.078704 Z M39.8706761,116.766844 C44.1268856,115.725876 53.6774041,114.21174 61.1517228,113.360038 C78.7994201,111.372734 134.960621,119.511215 135.168241,119.227315 C135.375861,119.038049 134.441571,54.1194625 131.327272,29.7040177 C129.873932,18.9157978 129.354882,17.9694627 122.088183,13.4270544 C110.461465,6.04564084 74.5432107,2.73346808 53.365974,6.9919759 C42.9849757,9.07391307 36.7563767,11.7236513 27.4134781,17.6855623 L22.2811694,21.5880854 L23.5725088,121.309253 L39.8706761,116.766844 Z M17.5997043,65.8013821 C17.5997043,50.8200023 18.1706592,50.7529753 17.5997043,50.7529753 C14.2258377,50.7529753 10.6869946,53.2086296 7.93345172,57.6428564 C5.8104668,61.0616507 5.50346166,65.6301628 5.50346166,67.2280714 C5.50346166,78.0260213 10.8676018,86.5101525 17.5997043,86.5101525 C17.5997043,86.5101525 17.5997043,79.607229 17.5997043,65.8013821 Z',
								id: 'mug',
								stroke: '#000000',
							}),

							el(
								'g',
								{
									id: 'Face',
									transform: 'translate(50.942850, 60.400797)',
								},
								[
									el('path', {
										d: 'M62.160778,33.5475835 C58.4082262,26.6041533 60.1139316,19.0295022 64.8899066,19.0295022 C67.6190353,19.0295022 70.6893049,21.554386 71.7127282,24.7104906 C74.1007157,31.0226999 65.2310478,39.2285718 62.160778,33.5475835 Z',
										id: 'Shape',
									}),
									el('path', {
										d: 'M2.10303837,34.0061906 C-1.64951349,27.0627603 0.0561919019,19.4881092 4.832167,19.4881092 C7.56129563,19.4881092 10.6315653,22.0129929 11.6549885,25.1690976 C14.0429761,31.4813068 5.17330808,39.6871789 2.10303837,34.0061906 Z',
										id: 'Shape',
									}),
									el('path', {
										d: 'M31.2744481,13.4867788 C22.3526728,9.75718844 22.3526728,6.85639597 31.6992946,2.91960618 C42.1080324,-1.22438307 54.6410025,-0.809984147 59.1018902,3.74840403 C62.2882385,7.47799435 62.2882385,7.89239328 58.8894669,10.7931858 C53.1540399,15.7659729 39.3465305,17.0091696 31.2744481,13.4867788 Z M34.976751,7.90748093 C31.9007499,10.1104937 36.6921961,10.3294258 44.7947408,9.91788078 C56.0107659,9.20199741 56.1391309,4.42943021 44.7105432,4.91244623 C39.9559268,5.04429429 35.7457513,7.35672775 34.976751,7.90748093 Z',
										id: 'Shape',
									}),
								],
							),
						],
					),
					el(
						'g',
						{
							id: 'Steam',
							transform: 'translate(59.000000, 0.000000)',
						},
						[
							el('path', {
								d: 'M4.16072763,0 C3.8378459,2.63060705 3.63689662,5.30305159 3.55986302,7.99095841 C3.50399,11.7470679 4.34571573,15.3721626 5.88012483,17.9837251 C6.89080993,19.79566 7.8953323,21.6238698 8.89369196,23.4683544 C9.94816663,25.3544895 10.6511261,27.7615875 10.9088994,30.3688969 C11.2199644,33.5056623 10.7257443,36.7238795 9.55926508,39.1573237 C8.32730672,41.7867086 6.67010618,43.6939485 4.8078126,44.6256781 L4.16072763,45 C4.54897862,43.79566 4.91874145,42.6889692 5.26077208,41.5660036 C5.72297563,40.0524412 6.18517917,38.5388789 6.61040644,36.9764919 C7.10981843,35.2326056 7.05009434,33.1543735 6.45325723,31.5081374 C6.2293797,30.9214149 5.97231446,30.3761351 5.68599934,29.880651 C4.66915153,28.0415914 3.62457152,26.2676311 2.63545592,24.4122966 C1.45178454,22.2681987 0.62382925,19.5948547 0.241241542,16.681736 C-0.438989396,11.7709757 0.340504342,6.58788172 2.31191343,2.91320073 C2.76487291,2.01808319 3.30102903,1.28571429 3.80020887,0.455696202 L4.06828692,5.05924416e-15 L4.16072763,0 Z',
								id: 'Shape',
							}),
							el('path', {
								d: 'M42,22.2784335 C41.5087566,23.247748 41.0367776,24.1755205 40.5551664,25.1586823 C40.1845249,25.9527384 39.8622545,26.7913079 39.591944,27.6650528 C39.2956943,28.8926755 39.2292571,30.2107229 39.3992994,31.4869214 C39.5437829,33.4255504 39.7267951,35.3641795 39.8038529,37.3028085 C39.8563132,38.9567993 39.5167155,40.587414 38.8406305,41.9278235 C38.0105027,43.6950754 36.6602155,44.8138092 35.1803853,44.9603932 C33.720005,45.1484608 32.2558855,44.6634774 31,43.5756581 L31.1348511,43.4094899 C32.0980736,42.4955649 33.1287215,41.5954871 34.1015762,40.6400199 C34.7603771,40.0681104 35.0780935,38.9403296 34.8817863,37.8705499 C34.5399208,35.5070657 34.3752325,33.0977166 34.390543,30.683775 C34.5530247,26.3626272 36.7924318,22.7913004 39.765324,22.1122653 C40.4194081,21.9625782 41.0858457,21.9625782 41.7399299,22.1122653 L41.8844134,22.1122653 L42,22.2784335 Z',
								id: 'Shape',
							}),
						],
					),
				],
			),
		),
	);

	const {
		TextControl,
		CheckboxControl,
		RadioControl,
		SelectControl,
		TextareaControl,
		ToggleControl,
		RangeControl,
		Panel,
		PanelBody,
		PanelRow,
	} = components;

	registerBlockType('yarpp/yarpp-block', {
		title: __('Related Posts [YARPP]', 'yet-another-related-posts-plugin'),
		description: __(
			'Display related posts by YARPP',
			'yet-another-related-posts-plugin',
		),
		category: 'yarpp',
		icon: iconEl,
		keywords: [
			__('yarpp', 'yet-another-related-posts-plugin'),
			__('yet another', 'yet-another-related-posts-plugin'),
			__('related posts', 'yet-another-related-posts-plugin'),
			__('contextual', 'yet-another-related-posts-plugin'),
			__('popular', 'yet-another-related-posts-plugin'),
			__('similar', 'yet-another-related-posts-plugin'),
			__('thumbnail', 'yet-another-related-posts-plugin'),
			__('you may also', 'yet-another-related-posts-plugin'),
			__('posts', 'yet-another-related-posts-plugin'),
		],
		supports: {
			html: false,
		},

		transforms: {
			from: [
				{
					type: 'block',
					blocks: ['core/legacy-widget'],
					isMatch: ({ idBase, instance }) => {
						if (!instance?.raw) {
							// Can't transform if raw instance is not shown in REST API.
							return false;
						}
						return idBase === 'yarpp_widget';
					},
					transform: ({ instance, ...rest }) => {
						const template = instance.raw.template;
						const heading =
							'heading' in instance.raw
								? instance.raw.heading
								: template === 'thumbnails'
								? instance.raw.thumbnails_heading
								: instance.raw.title;
						return createBlock('yarpp/yarpp-block', {
							name: 'yarpp_widget',
							template: template,
							heading: heading,
							domain: 'widget',
						});
					},
				},
			],
		},

		attributes: {
			reference_id: {
				type: 'string',
				default: '',
			},
			heading: {
				type: 'string',
				default: __('You may also like', 'yet-another-related-posts-plugin'),
			},
			limit: {
				type: 'number',
				default: 6,
			},
			template: {
				type: 'string',
				default: yarpp_localized.selected_theme_style,
			},
			yarpp_preview: {
				type: 'string',
			},
			domain: {
				type: 'string',
				default: 'block',
			},
			yarpp_is_admin: {
				type: 'boolean',
				default: yarpp_localized.yarpp_is_admin,
			},
		},
		example: {
			attributes: {
				yarpp_preview: 'yarpp_preview',
			},
		},
		edit: function (props) {
			const attributes = props.attributes;
			const setAttributes = props.setAttributes;
			var template = Object.keys(yarpp_localized.template).map(function (key) {
				return { value: key, label: yarpp_localized.template[key] };
			});

			if (props.isSelected) {
				//	console.debug(props.attributes);
			}

			// Functions to update attributes.
			function changeThumbnail(template) {
				setAttributes({ template });
			}

			function shouldShowHeading(template) {
				const is_widget = yarpp_localized.default_domain === 'widget';

				if (is_widget) {
					return ['', 'builtin', 'list', 'thumbnail', 'thumbnails'].includes(
						template,
					);
				}

				return ['thumbnail', 'thumbnails'].includes(template);
			}

			useEffect(() => {
				setAttributes({ domain: yarpp_localized.default_domain });
			}, []);

			return [
				/**
				 * Server side render
				 */
				el(
					'div',
					{ className: props.className },
					el(ServerSideRender, {
						block: 'yarpp/yarpp-block',
						attributes: attributes,
					}),
				),

				/**
				 * Inspector
				 */
				el(
					InspectorControls,
					{},
					el(
						PanelBody,
						{ title: 'YARPP Posts Settings', initialOpen: true },
						el(TextControl, {
							label: __(
								'Reference ID (Optional)',
								'yet-another-related-posts-plugin',
							),
							value: attributes.reference_id,
							help: __(
								'The ID of the post to use for finding related posts. Defaults to current post.',
								'yet-another-related-posts-plugin',
							),
							onChange: function (val) {
								setAttributes({ reference_id: val });
							},
						}),
						el(TextControl, {
							label: __(
								'Maximum number of posts',
								'yet-another-related-posts-plugin',
							),
							value: attributes.limit,
							onChange: function (val) {
								setAttributes({ limit: parseInt(val) });
							},
							type: 'number',
							min: 1,
							step: 1,
						}),
						el(SelectControl, {
							value: attributes.template,
							label: __('Theme', 'yet-another-related-posts-plugin'),
							onChange: changeThumbnail,
							options: template,
						}),
						shouldShowHeading(attributes.template) &&
							el(TextControl, {
								label: __('Heading', 'yet-another-related-posts-plugin'),
								value: attributes.heading,
								onChange: function (val) {
									setAttributes({ heading: val });
								},
							}),
					),
				),
			];
		},

		save() {
			return null; //save has to exist. This all we need
		},
	});
})(
	window.wp.blocks,
	window.wp.i18n,
	window.wp.element,
	window.wp.components,
	window.wp.editor,
	window.wp.blockEditor,
	window.wp.serverSideRender,
);

// Support for Legacy Widgets per WP 5.8 widgets change
(function ($) {
	$(document).on('widget-added', function () {
		$('.yarpp-widget-select-control', '#wpbody').each(ensureTemplateChoice);
		$('.yarpp-widget-select-control select', '#wpbody').on(
			'change',
			ensureTemplateChoice,
		);

		function ensureTemplateChoice(e) {
			if (typeof e === 'object' && 'type' in e) e.stopImmediatePropagation();
			var this_form = $(this).closest('form'),
				widget_id = this_form.find('.widget-id').val();
			// if this widget is just in staging:
			if (/__i__$/.test(widget_id)) return;

			const select = $('#widget-' + widget_id + '-template_file').val();
			const show_heading = select === 'builtin' || select === 'thumbnails';
			$('#widget-' + widget_id + '-heading')
				.closest('p')
				.toggle(show_heading);
		}
	});
})(jQuery);
