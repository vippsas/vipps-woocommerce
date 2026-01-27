import { blockConfig } from '../config';

export default function VippsSmile() {
	return (
		<img
			className={
				'block-editor-block-icon has-colors vipps-smile vipps-component-cion'
			}
			src={blockConfig['vippssmileurl']}
		/>
	);
}
