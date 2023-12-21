interface Attachment {
  url: string;
  sizes?: {
    thumbnail: {
      url: string;
    };
  };
  id: string;
}
interface MediaUploader {
  state(): {
    get(selection: string): {
      first(): {
        toJSON(): Attachment;
      };
    };
  };
  on(event: string, callback: () => void): void;
  open(): void;
}
// Some basic types to make TypeScript happy
// This can be extended as needed
declare const wp: {
  media(options: {
    library: {
      type: string;
    };
    button: object;
    multiple: boolean;
  }): MediaUploader;
};

interface UseWPImageUpload {
  onUpload: (id: string, url: string) => void;
}

/**
 * Custom hook for uploading images using WordPress media library.
 * @param options.onUpload - The callback function to be called when an image is uploaded.
 * @returns - An object containing the handleImageUpload function.
 */
export function useWPImageUpload({ onUpload }: UseWPImageUpload) {
  const handleImageUpload = () => {
    const mediaUploader = wp.media({
      library: {
        type: 'image'
      },
      button: {},
      multiple: false
    });

    mediaUploader.on('select', () => {
      const attachment: Attachment = mediaUploader.state().get('selection').first().toJSON();

      let url = '';
      if (attachment.url) {
        url = attachment.url;
      } else if (attachment.sizes && attachment.sizes.thumbnail) {
        url = attachment.sizes.thumbnail.url;
      }

      if (!url) return;

      onUpload(attachment.id, url);
    });

    mediaUploader.open();
  };

  return {
    handleImageUpload
  };
}
