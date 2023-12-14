import { useWPImageUpload } from '../../hooks/use-wp-image-upload';
import { gettext } from '../../lib/wp-data';
import { useWP } from '../../wp-options-provider';
import { CheckboxFormField, SelectFormField } from '../options-form-fields';
import { WPButton, WPFormField, WPLabel } from '../form-elements';

export function AdminSettingsAdvancedOptionsTab(): JSX.Element {
  const { getOption, setOption } = useWP();

  function handleImageRemove() {
    setOption('receiptimage', '');
    setOption('receiptimage_url', '');
  }

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

      <SelectFormField
        name="vippsspecialpagetemplate"
        titleKey="vippsspecialpagetemplate_title"
        descriptionKey="vippsspecialpagetemplate_description"
        options={Object.values(gettext('vippsspecialpagetemplate_options'))}
      />

      <SelectFormField
        name="vippsspecialpageid"
        titleKey="vippsspecialpageid_title"
        descriptionKey="vippsspecialpageid_description"
        options={Object.values(gettext('vippsspecialpageid_options'))}
      />

      <CheckboxFormField
        name="sendreceipts"
        titleKey="sendreceipts_title"
        descriptionKey="sendreceipts_description"
        labelKey="sendreceipts_label"
      />

      <WPFormField>
        <WPLabel htmlFor="woocommerce_vipps_receiptimage">{gettext('receiptimage_title')}</WPLabel>
        <div className="vipps-mobilepay-react-col">
          <div>
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

      <CheckboxFormField name="use_flock" titleKey="use_flock_title" descriptionKey="use_flock_description" labelKey="use_flock_label" />

      <CheckboxFormField
        name="developermode"
        titleKey="developermode_title"
        descriptionKey="developermode_description"
        labelKey="developermode_label"
      />
    </div>
  );
}
