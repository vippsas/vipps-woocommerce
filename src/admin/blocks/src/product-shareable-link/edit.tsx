/**
 * External dependencies
 */
import type { BlockAttributes } from '@wordpress/blocks';
import { useWooBlockProps } from '@woocommerce/block-templates';
import { createElement } from '@wordpress/element';
import { Table } from '@woocommerce/components';
import { SelectControl, Button, Spinner } from '@wordpress/components';

import { __, sprintf } from '@wordpress/i18n';
import { __experimentalUseProductEntityProp as useProductEntityProp } from '@woocommerce/product-editor';
import { useState } from 'react';

interface Metadata {
	key: string;
	value: any;
	id: number | null;
}
interface MetadataShareableLink {
	key: string;
	id: number | null;
	value: {
		product_id: number;
		variation_id: number;
		key: string;
		url: string;
		variant: string;
	};
}
interface SharedLinkBlockAttributes extends BlockAttributes {
	title: string;
	message: string;
}
// Extend the window object to include some WP global variables
declare global {
	interface Window {
		ajaxurl: string;
	}
}

/**
 * The edit function describes the structure of the woo-vipps/product-shareable-link block in the editor.
 *
 * @see https://developer.wordpress.org/block-editor/reference-guides/block-api/block-edit-save/#edit
 *
 */
export function Edit( {
	attributes,
}: {
	attributes: SharedLinkBlockAttributes;
} ) {
	// Get variations
	const [ variations = [] ] =
		useProductEntityProp< number[] >( 'variations' );
	const [ productId ] = useProductEntityProp< string >( 'id' );
	const blockProps = useWooBlockProps( attributes );
	const [ metadata = [], setMetadata ] =
		useProductEntityProp< Metadata[] >( 'meta_data' );
	const [ shareLinkNonce ] =
		useProductEntityProp< string >( 'share_link_nonce' );

	// Because of they way meta_data is stored, we need to filter any metadata that starts with _vipps_shareable_links
	const links: MetadataShareableLink[] = metadata.filter( ( meta ) =>
		meta.key.startsWith( '_vipps_shareable_links' )
	);

	// Some state to manage the "loading/copying" state of the copy button, this is initialized as null, and set to the URL being copied when the button is clicked
	const [ isCopyingURLValue, setIsCopyingURLValue ] = useState<
		string | null
	>( null );
	// State to manage the selected variant
	const [ variant, setVariant ] = useState< number | null >( null );
	// State to keep track of loading state of new shareable link creation
	const [ isLoading, setIsLoading ] = useState( false );

	/**
	 * Copies the given URL to the clipboard.
	 *
	 * @param url - The URL to be copied.
	 */
	async function copyToClipboard( url: string ) {
		setIsCopyingURLValue( url );
		// Use the Clipboard API if available
		if ( navigator.clipboard ) {
			navigator.clipboard.writeText( url );
		} else {
			// Fallback to using deprecated document.execCommand
			const textArea = document.createElement( 'textarea' );
			textArea.value = url;
			document.body.appendChild( textArea );
			textArea.select();
			document.execCommand( 'copy' );
			document.body.removeChild( textArea );
		}
		// Wait for 2.5 seconds before resetting the state
		await new Promise( ( resolve ) => setTimeout( resolve, 2500 ) );
		setIsCopyingURLValue( null );
	}

	async function createShareableLink() {
		try {
			setIsLoading( true );
			const params = new URLSearchParams( {
				action: 'vipps_create_shareable_link',
				vipps_share_sec: shareLinkNonce!,
				prodid: productId!,
				varid: variant?.toString() || '0',
			} );
			const response = await fetch( window.ajaxurl, {
				method: 'POST',
				body: params.toString(),
				credentials: 'include',
				headers: {
					'Content-Type':
						'application/x-www-form-urlencoded; charset=UTF-8',
				},
			} );
			// Throw early if the response is not ok
			if ( ! response.ok ) {
				throw new Error();
			}

			// Parse the response as JSON
			const result = ( await response.json() ) as {
				ok: boolean;
				variant: string;
				url: string;
				key: string;
				msg: string;
			};
			// Append this new shareable link to the list of existing links
			console.log( result );
			if ( result.ok ) {
				setMetadata( [
					...metadata,
					{
						key: '_vipps_shareable_links',
						value: {
							product_id: productId,
							variation_id: variant,
							key: result.key,
							url: result.url,
							variant: result.variant,
						},
						id: null,
					},
				] );
			} else {
				throw new Error( result.msg );
			}
		} catch ( error ) {
			if ( error instanceof Error ) {
				// TODO: use toast or similar instead of alert
				alert(
					__( 'Error creating shareable link.', 'woo-vipps' ) +
						error.message
				);
			}
		} finally {
			setIsLoading( false );
		}
	}

	function removeShareableLink( key: string ) {
		const newMetadata = metadata.filter( ( meta ) => {
			return (
				( meta.value as Metadata | MetadataShareableLink ).key !== key
			);
		} );
		setMetadata( newMetadata );
	}

	return (
		<div { ...blockProps }>
			<span>{ attributes.title }</span>
			<div>
				<div className="create-link-section">
					{ variations.length > 0 && (
						<SelectControl
							className='vipps-sharelink-variant'
							value={ variant?.toString() }
							onChange={ ( value ) =>
								setVariant( parseInt( value, 10 ) )
							}
							label={ __( 'Variation', 'woo-vipps' ) }
							options={ variations.map( ( variation ) => {
								return {
									label: variation.toString(),
									value: variation.toString(),
								};
							} ) }
						/>
					) }
					<Button
						variant="secondary"
						disabled={ isLoading }
						type="button"
						onClick={ createShareableLink }
					>
						{ isLoading && <Spinner /> }
						{ __( 'Create shareable link', 'woo-vipps' ) }
					</Button>
				</div>

				{ /* Table which render a list of available shareable links of this product */ }
				<Table
					headers={ [
						{
							key: 'variant',
							label: __( 'Variation', 'woo-vipps' ),
							visible: variations.length > 0,
						},
						{
							key: 'link',
							label: __( 'Link', 'woo-vipps' ),
						},
						{
							key: 'actions',
							label: __( 'Actions', 'woo-vipps' ),
						},
					] }
					rows={ links.map( ( item ) => {
						return [
							{
								key: 'variant',
								display: item.value.variant,
							},
							{
								key: 'link',
								display: (
									<Button
										href={ item.value.url }
										variant="link"
										target="_blank"
									>
										{ item.value.url }
									</Button>
								),
							},
							{
								key: 'actions',
								display: (
									<div>
										<Button
											disabled={
												// Disable copy button if any url is being copied
												isCopyingURLValue !== null
											}
											onClick={ () =>
												copyToClipboard(
													item.value.url
												)
											}
										>
											{ /* Conditionally show copied message */ }
											{ isCopyingURLValue ===
											item.value.url
												? __( 'Copied!', 'woo-vipps' )
												: __( 'Copy', 'woo-vipps' ) }
										</Button>
										<Button
											onClick={ () =>
												removeShareableLink(
													item.value.key
												)
											}
										>
											{ __( 'Delete', 'woo-vipps' ) }
										</Button>
									</div>
								),
							},
						];
					} ) }
				/>
			</div>

			<p dangerouslySetInnerHTML={ { __html: attributes.message } }></p>
		</div>
	);
}
