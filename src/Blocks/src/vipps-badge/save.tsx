import { useBlockProps } from '@wordpress/block-editor';

export default function save() {
	return (
		<>
			<p {...useBlockProps.save()}>
				{
					'Vipps On-Site Messaging Badge – hello from the saved content!'
				}
			</p>
			{/* @ts-ignore */}
			<vipps-badge></vipps-badge>
		</>
	);
}
