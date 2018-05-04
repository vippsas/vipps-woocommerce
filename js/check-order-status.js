// IOK 2018-05-04 Call ajax methods determining if and order is complete or not from the 'confirm' waiting screen
jQuery(document).ready(function () {
 var start = new Date();
 var fkey = jQuery('#fkey').val();
 var fkey404 = false;
 var iterate = 0;
 var statusok = 0;
 var aminute = 1 * 60 * 60 * 1000;
 var data  = jQuery('#vippsdata').serialize();

 function checkStatus() {
   console.log("Checking status");
   if (statusok) return 1;
   var now = new Date();
   iterate++;
   // If we have a fkey that hasn't responed 404 yet and a minute hasn't passed,  call that directly and often IOK 2018-05-04
   if (fkey && !fkey404 && (now.getTime() - start.getTime()) < aminute) {
     // Use post to hide data slightly and to avoid caches
     console.log("Checking " + fkey);
     setTimeout(checkStatus, 500);
   } else {
     console.log("Doing admin-ajax");
     setTimeout(checkStatus, 4000);
   }
 }
 checkStatus();

});
