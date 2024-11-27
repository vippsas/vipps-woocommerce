import { store, getContext } from '@wordpress/interactivity'

store('woo-vipps', {
  callbacks: {
    init: () => {
      const { pid } = getContext();
      console.log(' init called for ' + pid);
    },
    watch: () => {
      const { pid } = getContext();
      console.log(' watch called for ' + pid);
    }
  },
});

