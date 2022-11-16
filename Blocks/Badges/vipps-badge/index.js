( function( wp ) {

	const registerBlockType = wp.blocks.registerBlockType;
    const AlignmentToolbar = wp.blockEditor.AlignmentToolbar;

	const el  = wp.element.createElement;
        const components = wp.components;

        const SelectControl = components.SelectControl;
        const TextControl = components.TextControl;

        const RichText = wp.blockEditor.RichText;

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
                  application: {
                       default: VippsBadgeBlockConfig['defaultapp'],
                       type: "string",
                       source: "attribute",
                       selector: "a",
                       attribute: "data-application"
                  },
                  title: {
                       default: VippsBadgeBlockConfig['DefaultTitle'],
                       type: "string",
                       source: "attribute",
                       selector: "a",
                       attribute: "title" 
                  },
                  prelogo: {
                      default: [VippsBadgeBlockConfig['DefaultTextPrelogo']],
                      type: "array",
                      source: "children",
                      selector: ".prelogo",
                  },
                  postlogo: {
                      default: [VippsBadgeBlockConfig['DefaultTextPostlogo']],
                      type: "array",
                      source: "children",
                      selector: ".postlogo",
                  },
                 },

        edit: function( props ) {
            let logo =  VippsBadgeBlockConfig['logosrc'];
            let attributes = props.attributes;
            let formats = ['core/bold', 'core/italic'];

            // Let the user choose the application. If the current one isn't in the list, add it (though we don't know the label then. IOK 2020-12-18
            let appOptions =  VippsBadgeBlockConfig['applications'];
            let current = attributes.application;
            let found=false;
            for(let i=0; i<appOptions.length; i++) {
                if (current == appOptions[i].value) {
                    found=true; break;
                } 
            }
            if (!found) appOptions.push({label: current, value: current});

            return el(
                'div',
                { className: 'vipps-badge-wrapper inline ' + props.className },

                   el('vipps-badge', { className: props.className }, []), 

                   el(BlockControls, {}, 
                        el(AlignmentToolbar,{ value: attributes.alignment, onChange: onChangeAlignment  } )
                   ),
                  
                    el(InspectorControls, {},
                    el(SelectControl, { onChange: x=>props.setAttributes({application: x}) , 
                        label: VippsBadgeBlockConfig['Application'], value:attributes.application, 
                        options: appOptions,
                        help:  VippsBadgeBlockConfig['ApplicationsText']  }),
                    el(TextControl, { onChange: x=>props.setAttributes({title: x}) , 
                        label:  VippsBadgeBlockConfig['Title'] , value:attributes.title,
                        help:  VippsBadgeBlockConfig['TitleText']   })
                ),
            )

        },

	    save: function( props ) {
		var attributes = props.attributes;
		return el( 'span', { className: 'continue-with-vipps-wrapper inline ' + props.className   },
                    el("a", { className: "button vipps-orange vipps-button continue-with-vipps continue-with-vipps-action", 
                              title:attributes.title, 'data-application':attributes.application, href: "javascript: void(0);" },
                    el( RichText.Content, {
                       tagName: 'span',
                       className: 'prelogo',
                       value:attributes.prelogo,
                     }),
                      el("img", {alt:attributes.title, src: VippsBadgeBlockConfig['logosrc'] }),
                    el( RichText.Content, {
                       tagName: 'span',
                       className: 'postlogo',
                       value: attributes.postlogo
                     }),

                    ));
            },
                
  });

} )(
	window.wp
);
