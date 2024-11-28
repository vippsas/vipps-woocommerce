import { store, getContext } from '@wordpress/interactivity'

store('woo-vipps', {
  callbacks: {
    init: () => {
      const { pid } = getContext();
      console.log(' init called');
      // document.body.dispatchEvent(new Event('vippsInit'));

    },
    watch: () => {
      const { pid } = getContext();
      console.log(' watch called, dispatching event vippsInit');
      document.body.dispatchEvent(new Event('vippsInit'));
    }
  },
});

