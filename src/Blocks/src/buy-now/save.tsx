import { useBlockProps } from '@wordpress/block-editor';

export default function save(props: any) {
	
	return (
		<p { ...useBlockProps.save() }>
			{ 'Vipps Buy Now Button – hello from the saved content!' }
		</p>
	);
}
