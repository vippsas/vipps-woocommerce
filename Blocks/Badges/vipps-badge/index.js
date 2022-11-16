( function( wp ) {

	const registerBlockType = wp.blocks.registerBlockType;
    const AlignmentToolbar = wp.blockEditor.AlignmentToolbar;

	const el  = wp.element.createElement;
        const components = wp.components;

        const SelectControl = components.SelectControl;
        const CheckboxControl = components.CheckboxControl;
        const TextControl = components.TextControl;

        const useBlockProps = wp.blockEditor.useBlockProps;
        const BlockControls = wp.blockEditor.BlockControls;
        const InspectorControls = wp.blockEditor.InspectorControls;

        const onChangeAlignment = function (event) {
            console.log("Alignment change: %j", event);
        };

	registerBlockType( 'woo-vipps/vipps-badge', {
		title: VippsBadgeBlockConfig['BlockTitle'],
		category: 'widgets',
                icon: el('img', {"class": "vipps-smile vipps-component-icon", "src": VippsBadgeBlockConfig['vippssmileurl']}),
		supports: {
			html: true,
                        anchor: true,
                        align:true,
                        customClassName: true,
		},

                attributes: {
                  variant: {
                       default: VippsBadgeBlockConfig['defaultvariant'],
                       type: "string",
                       source: "attribute",
                       selector: "vipps-badge",
                       attribute: "variant"
                  },
                  language: {
                       default: 'default',
                       type: "string",
                       source: "attribute",
                       selector: "vipps-badge",
                       attribute: "language"
                  },
                  later: {
                       type: "boolean",
                       source: "attribute",
                       selector: "vipps-badge",
                       attribute: "vipps-senere"
                  },
                  amount:  {
                       default: 0,
                       type: "number",
                       source: "attribute",
                       selector: "vipps-badge",
                       attribute: "amount"
                  } 


                 },

        edit: function( props ) {
            let logo =  VippsBadgeBlockConfig['logosrc'];
            let attributes = props.attributes;
            let formats = ['core/bold', 'core/italic'];

            // Let the user choose the variant. If the current one isn't in the list, add it (though we don't know the label then. IOK 2020-12-18
            let appOptions =  VippsBadgeBlockConfig['variants'];
            let current = attributes.variant;
            let found=false;
            for(let i=0; i<appOptions.length; i++) {
                if (current == appOptions[i].value) {
                    found=true; break;
                } 
            }
            if (!found) appOptions.push({label: current, value: current});

            let attrs =  { className: props.className, variant: current };
            if (props.attributes.later) {
                attrs["vipps-senere"] = true;
            }

            return el(
                'div',
                { className: 'vipps-badge-wrapper ' + props.className },

                   el('vipps-badge', attrs, []), 

                   el(BlockControls, {}, 
                        el(AlignmentToolbar,{ value: attributes.alignment, onChange: onChangeAlignment  } )
                   ),
                  
                    el(InspectorControls, {},
                      el(SelectControl, { onChange: x=>props.setAttributes({variant: x}) , 
                          label: VippsBadgeBlockConfig['Variant'], value:attributes.variant, 
                          options: appOptions,
                          help:  VippsBadgeBlockConfig['VariantText']  }),
                      el(CheckboxControl, { onChange: x=>props.setAttributes({later: x}),
                          label: VippsBadgeBlockConfig['VippsLater'], value: attributes.later,
                          options: [true, false],
                          checked: props.attributes.later,
                          help: VippsBadgeBlockConfig['VippsLaterText']  }),
                      el(TextControl, { onChange: x=>props.setAttributes({'amount': x}),
                          label: VippsBadgeBlockConfig['Amount'], value: attributes.amount,
                          help: VippsBadgeBlockConfig['AmountText']  }),
                      el(SelectControl, { onChange: x=>props.setAttributes({language: x}) , 
                          label: VippsBadgeBlockConfig['Language'], value:attributes.language, 
                          options: VippsBadgeBlockConfig['languages'],
                          help:  VippsBadgeBlockConfig['LanguageText']  }),
                    ),
                         
                    
            )

        },

	    save: function( props ) {
		var attributes = props.attributes;

                let attrs =  { className: props.className, variant: attributes.variant };
                if (props.attributes.later) {
                    attrs["vipps-senere"] = "true";
                }
                
                 console.log("Saving the vipps-badge with %j", attrs);

		return el( 'div', { className: 'vipps-badge-wrapper ' + props.className   },
                    el('vipps-badge', attrs, []),

                    );
            },
                
  });

} )(
	window.wp
);
