( function( wp ) {

    const registerBlockType = wp.blocks.registerBlockType;
    const AlignmentToolbar = wp.blockEditor.AlignmentToolbar;

    const el  = wp.element.createElement;
    const components = wp.components;

    const SelectControl = components.SelectControl;
    const CheckboxControl = components.CheckboxControl;
    const TextControl = components.TextControl;

    const useBlockProps = wp.blockEditor.useBlockProps;
    const InspectorControls = wp.blockEditor.InspectorControls;

    registerBlockType( 'woo-vipps/vipps-badge', {
        apiVersion: 3,
        title: VippsBadgeBlockConfig['BlockTitle'],
        category: 'widgets',
        icon: el('img', {"class": "vipps-smile vipps-component-icon", "src": VippsBadgeBlockConfig['vippssmileurl']}),
        supports: {
            html: true,
            anchor: true,
            align:['left', 'right', 'center'],
            customClassName: true,
        },

        attributes: {
            align: {
                type: "string",
            },
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
                default: "",
                type: "text",
                source: "attribute",
                selector: "vipps-badge",
                attribute: "amount"
            } 


        },

        edit: function( props ) {
            let bp = useBlockProps();
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
            if (props.attributes.language !== 'default') {
                attrs['language'] = props.attributes.language;
            } else {
                attrs['language'] = "";
            }
            if (props.attributes.amount ) {
                let am = parseInt(props.attributes.amount);
                if (!isNaN(am)) {
                    attrs['amount'] = props.attributes.amount;
                }
            } 

            let extraclass =  (props.className && props.className != "undefined")  ? props.className : "";
            switch(props.attributes.align) {
                case 'center':
                    extraclass += " aligncenter"; break;
                case 'left':
                    extraclass += " alignleft"; break;
                case 'right':
                    extraclass += " alignright"; break;
            }

            return el(
                'div',
                { ...bp, className: bp.className + ' vipps-badge-wrapper ' + extraclass},

                el('vipps-badge', attrs, []), 

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
            let bp = useBlockProps.save();
            var attributes = props.attributes;

            let attrs =  { className: props.className, variant: attributes.variant };
            if (props.attributes.later) {
                attrs["vipps-senere"] = "true";
            }
            if (props.attributes.language !== 'default') {
                attrs['language'] = props.attributes.language;
            } else {
                attrs['language'] = "";
            }
            if (props.attributes.amount ) {
                let am = parseInt(props.attributes.amount);
                if (!isNaN(am)) {
                    attrs['amount'] = props.attributes.amount;
                }
            } 

            let extraclass =  (props.className && props.className != "undefined")  ? props.className : "";
            switch(props.attributes.align) {
                case 'center':
                    extraclass += " aligncenter"; break;
                case 'left':
                    extraclass += " alignleft"; break;
                case 'right':
                    extraclass += " alignright"; break;
            }

            return el( 'div', { ...bp, className: bp.className + ' vipps-badge-wrapper ' + extraclass},
                el('vipps-badge', attrs, []),

            );
        },

    });

} )(
	window.wp
);
