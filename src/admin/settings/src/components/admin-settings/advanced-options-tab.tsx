import { useState } from 'react';
import { useWPImageUpload } from '../../hooks/use-wp-image-upload';
import { gettext } from '../../lib/wp-data';
import { useWP } from '../../wp-options-provider';
import { CheckboxFormField, SelectFormField } from '../options-form-fields';
import { WPButton, WPFormField, WPLabel } from '../form-elements';
import { NotificationBanner } from '../notification-banner';

/**
 * A React component that renders the advanced options tab for the admin settings page.
 *
 * @returns The rendered advanced options tab.
 */
export function AdminSettingsAdvancedOptionsTab(): JSX.Element {
  const { getOption, setOption } = useWP();
  const [error, setError] = useState<string | null>(null);

  /**
   * Removes the image from the options and clears the image URL.
   */
  function handleImageRemove() {
    setOption('receiptimage', '');
    setOption('receiptimage_url', '');
    setError(null); // Clear any existing error when removing image
  }

  /**
   * Handles the image upload and updates the receipt image option and URL.
   */
  const { handleImageUpload } = useWPImageUpload({
    onUpload(id, url) {
      // Check dimensions
      const img = new Image();
      img.src = url;
      
      img.onload = () => {
        if (img.height < 167) {
          setError(gettext('receipt_image_error'));
          return;
        }
        
        setError(null); // Clear error if image is valid
        console.log('Image Upload - Setting values:', { id, url });
        setOption('receiptimage', id);
        setOption('receiptimage_url', url);
      };
    }
  });

  const imageId = getOption('receiptimage');
  const imageUrl = getOption('receiptimage_url');

  return (
    <div>
      <p className="vipps-mobilepay-react-tab-description">{gettext('advanced_options.description')}</p>

      <CheckboxFormField
        labelKey="vippsorderattribution.label"
        name="vippsorderattribution"
        titleKey="vippsorderattribution.title"
        descriptionKey="vippsorderattribution.description"
      />

      {/*  Renders a checkbox to override the page template used for the special Vipps pages */}
      <SelectFormField
        name="vippsspecialpagetemplate"
        titleKey="vippsspecialpagetemplate.title"
        descriptionKey="vippsspecialpagetemplate.description"
        options={Object.entries(gettext('vippsspecialpagetemplate.options')).map(([templateid,templatename]) => ({
          label: templatename,
          value:  templateid ? templateid : ""
        }))}
      />

      {/* Renders a checkbox to use a real page ID for the special Vipps pages */}
      <SelectFormField
        name="vippsspecialpageid"
        titleKey="vippsspecialpageid.title"
        descriptionKey="vippsspecialpageid.description"
        options={Object.entries(gettext('vippsspecialpageid.options')).map(([pageid,pagename]) => ({
          label: pagename,
          value: pageid ? pageid : ""
        })

       )}
      />

      {/* Renders a checkbox to enable the sending of receipts */}
      <CheckboxFormField
        name="sendreceipts"
        titleKey="sendreceipts.title"
        labelKey="sendreceipts.label"
        descriptionKey="sendreceipts.description"
      />

      {/* Renders an image upload field to upload the receipt image */}
      {error && <NotificationBanner variant="error" text={error} />}
      <WPFormField>
        <WPLabel htmlFor="woocommerce_vipps_receiptimage">{gettext('receiptimage.title')}</WPLabel>
        <div className="vipps-mobilepay-react-col">
          <div>
            {/* Only show the image upload button if there is no image */}
            {imageId ? (
              <>
                <img
                  src={imageUrl}
                  id={imageId}
                  style={{
                    width: 200
                  }}
                />
                <WPButton type="button" onClick={handleImageRemove} variant="link">
                  {gettext('remove_image')}
                </WPButton>
              </>
            ) : (
              <WPButton type="button" onClick={handleImageUpload} variant="link">
                {gettext('upload_image')}
              </WPButton>
            )}
            <input type="hidden" name="woocommerce_vipps_receiptimage" id="woocommerce_vipps_receiptimage" value={imageId} />
          </div>
          <span className="vipps-mobilepay-react-field-description">
            {gettext('receiptimage.description')}
            <br />
            <small>{gettext('receipt_image_size_requirement')}</small>
          </span>
        </div>
      </WPFormField>

      {/* Renders a checkbox to enable the use of flock() */}
      <CheckboxFormField name="use_flock" titleKey="use_flock.title" descriptionKey="use_flock.description" labelKey="use_flock.label" />

      {/* Renders a checkbox to enable developer mode */}
      <CheckboxFormField
        name="developermode"
        titleKey="developermode.title"
        descriptionKey="developermode.description"
        labelKey="developermode.label"
      />
    </div>
  );
}
