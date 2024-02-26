import { useWPImageUpload } from '../../hooks/use-wp-image-upload';
import { gettext } from '../../lib/wp-data';
import { useWP } from '../../wp-options-provider';
import { CheckboxFormField, SelectFormField } from '../options-form-fields';
import { WPButton, WPFormField, WPLabel } from '../form-elements';

/**
 * A React component that renders the advanced options tab for the admin settings page.
 *
 * @returns The rendered advanced options tab.
 */
export function AdminSettingsAdvancedOptionsTab(): JSX.Element {
  const { getOption, setOption } = useWP();

  /**
   * Removes the image from the options and clears the image URL.
   */
  function handleImageRemove() {
    setOption('receiptimage', '');
    setOption('receiptimage_url', '');
  }
  /**
   * Handles the image upload and updates the receipt image option and URL.
   */
  const { handleImageUpload } = useWPImageUpload({
    onUpload(id, url) {
      setOption('receiptimage', id);
      setOption('receiptimage_url', url);
    }
  });

  const imageId = getOption('receiptimage');
  const imageUrl = getOption('receiptimage_url');

  return (
    <div>
      <p className="vipps-mobilepay-react-tab-description">{gettext('advanced_options_description')}</p>

      {/*  Renders a checkbox to override the page template used for the special Vipps pages */}
      <SelectFormField
        name="vippsspecialpagetemplate"
        titleKey="vippsspecialpagetemplate_title"
        descriptionKey="vippsspecialpagetemplate_description"
        options={Object.values(gettext('vippsspecialpagetemplate_options'))}
      />

      {/* Renders a checkbox to use a real page ID for the special Vipps pages */}
      <SelectFormField
        name="vippsspecialpageid"
        titleKey="vippsspecialpageid_title"
        descriptionKey="vippsspecialpageid_description"
        options={Object.values(gettext('vippsspecialpageid_options'))}
      />

      {/* Renders a checkbox to enable the sending of receipts */}
      <CheckboxFormField
        name="sendreceipts"
        titleKey="sendreceipts_title"
        descriptionKey="sendreceipts_description"
        labelKey="sendreceipts_label"
      />

      {/* Renders an image upload field to upload the receipt image */}
      <WPFormField>
        <WPLabel htmlFor="woocommerce_vipps_receiptimage">{gettext('receiptimage_title')}</WPLabel>
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
          <span className="vipps-mobilepay-react-field-description">{gettext('receiptimage_description')}</span>
        </div>
      </WPFormField>

      {/* Renders a checkbox to enable the use of flock() */}
      <CheckboxFormField name="use_flock" titleKey="use_flock_title" descriptionKey="use_flock_description" labelKey="use_flock_label" />

      {/* Renders a checkbox to enable developer mode */}
      <CheckboxFormField
        name="developermode"
        titleKey="developermode_title"
        descriptionKey="developermode_description"
        labelKey="developermode_label"
      />
    </div>
  );
}
