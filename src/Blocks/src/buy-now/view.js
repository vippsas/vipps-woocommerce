import { store, getContext } from '@wordpress/interactivity'

store('woo-vipps', {
  callbacks: {
    init: () => {
      const { pid } = getContext();
      console.log(' init called for ' + pid);
      document.body.dispatchEvent(new Event('vippsInit'));
      
    },
    watch: () => {
      const { pid } = getContext();
      console.log(' watch called for ' + pid);
    }
  },
});

