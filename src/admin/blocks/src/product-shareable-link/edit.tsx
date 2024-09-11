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
	id: number | undefined;
}
interface MetadataShareableLink {
	key: string;
	id: number | undefined;
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
	// Add an empty value to the variations array to represent the "None" option
	const variationsWithEmptyValue = [ '', ...variations ];

	const shouldEnableVariations = variations.length > 0;
	const [ productId ] = useProductEntityProp< string >( 'id' );
	const blockProps = useWooBlockProps( attributes );
	const [ metadata = [], setMetadata ] =
		useProductEntityProp< Metadata[] >( 'meta_data' );
	const [ shareLinkNonce ] = useProductEntityProp< string >(
		'vipps_share_link_nonce'
	);

    // Used to display the shareable urls IOK 2024-06-19
	const [ vipps_buy_product_url ] = useProductEntityProp< string >(
		'vipps_buy_product_url'
	);

	// Because of they way meta_data is stored, we need to filter any metadata that starts with _vipps_shareable_link_
	const links: MetadataShareableLink[] = metadata.filter(
		( meta ) =>
			// Keep only the metadata that starts with _vipps_shareable_link_
			meta.key.startsWith( '_vipps_shareable_link_' ) &&
			// Keep only the metadata that has a value, if the value is undefined, it means it was just deleted
			meta.value !== undefined
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

	/**
	 * Creates a new shareable link for the current product and variant.
	 * This function only retrieves a new shareable link from the server, and then appends the shareable link to the metadata.
	 */
	async function createShareableLink() {
		try {
			setIsLoading( true );
			const params = new URLSearchParams( {
				action: 'vipps_generate_unused_shareable_meta_key',
				vipps_share_shareable_link_nonce: shareLinkNonce!,
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
				variation_id: string;
				variant: string;
				url: string;
				key: string;
				msg: string;
			};
			// Append this new shareable link to the list of existing links
			if ( result.ok ) {
				setMetadata( [
					...metadata,
					// The shareable link is stored as a postmeta variable with the key included in the meta key. This is retrieved by
					// the "vipps buy product" handler. IOK 2024-06-19
					{
						key: '_vipps_shareable_link_' + result.key,
						value: {
							product_id: productId,
							variation_id: result.variation_id,
							key: result.key,
						},
						id: -1, // This is a new meta, so it doesn't have an ID yet; WP will assign one when saved
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

	/**
	 * Removes a shareable link from the metadata.
	 *
	 * @param key - The key of the shareable link to remove.
	 */
	function removeShareableLink( key: string ) {
		const newMetadata = metadata.map(
			( meta: Metadata | MetadataShareableLink ) => {
				// Since we keep 2 separate keys for every 1 shareable link, we need to check for both and remove them
				// Key 2
				const isShareableLinkSpecificKeyMeta =
					meta.key === ('_vipps_shareable_link_' + key) &&
					meta?.value?.key == key;

				// Remove the value if it matches the key, this will cause the meta to be deleted
				if ( isShareableLinkSpecificKeyMeta ) {
					return {
						...meta,
						value: undefined,
					};
				}

				return meta;
			}
		);
		// Update the metadata
		setMetadata( newMetadata );
	}

	return (
		<div { ...blockProps }>
			<span>{ attributes.title }</span>
			<div>
				<div className="create-link-section">
					{ shouldEnableVariations && (
						<SelectControl
							className="vipps-sharelink-variant"
							value={ variant?.toString() }
							onChange={ ( value ) =>
								setVariant( parseInt( value, 10 ) ?? null )
							}
							label={ __( 'Variation', 'woo-vipps' ) }
							options={ variationsWithEmptyValue.map( ( variation ) => {
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
							visible: shouldEnableVariations,
						},
						{
							key: 'link',
							label: __( 'Link', 'woo-vipps' ),
						},
						{
							key: 'actions',
							label: __( 'Actions', 'woo-vipps' ),
							cellClassName: 'table-actions-col',
						},
					].filter( ( header ) => header.visible !== false ) } // Filter out any headers that are not visible
					rows={ links.map( ( item ) => {
                                                console.log("Item %j", item);
                                                let url =  new URL(vipps_buy_product_url);
                                                url.searchParams.set('pr', item.value['key']);
                                                item.value['url'] = url.toString();
                           
						return [
							{
								key: 'variant',
								display: item.value.variant,
								visible: shouldEnableVariations,
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
						].filter( ( row ) => row.visible !== false ); // Filter out any rows that are not visible
					} ) }
				/>
			</div>

			<p dangerouslySetInnerHTML={ { __html: attributes.message } }></p>
		</div>
	);
}
