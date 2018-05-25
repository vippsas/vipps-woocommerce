jQuery( document ).ready( function() {
 // This fires when a product has been added to the cart with ajax. 
 jQuery( 'body' ).on( 'added_to_cart', function() {
  console.log("Stuff added to cart");
 });
});
