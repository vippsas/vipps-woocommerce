// IOK 2018-05-04 Call ajax methods determining if and order is complete or not from the 'confirm' waiting screen
jQuery(document).ready(function () {
 var start = new Date();
 var fkey = jQuery('#fkey').val();
 var fkey404 = false;
 var iterate = 0;
 var statusok = 0;
 var aminute = 1 * 60 * 60 * 1000;
 var data  = jQuery('#vippsdata').serialize();

 function checkStatusReady() {
   console.log("Checking status");
   var now = new Date();
   iterate++;
   // If we have a fkey that hasn't responed 404 yet and a minute hasn't passed,  call that directly and often IOK 2018-05-04
   if (!statusok && fkey && !fkey404 && (now.getTime() - start.getTime()) < aminute) {
     // Use post to hide data slightly and to avoid caches
     console.log("time is " + (now.getTime() - start.getTime()));
     var url = fkey + "?v="+iterate;
     console.log("Checking " + url);
     jQuery.ajax(url,
      {'cache':false,
       'complete': function () { if (!statusok) setTimeout(checkStatusReady,500); },
       'dataType':'json',
       'error': function (xhr, statustext, error) {
         console.log("Error checking status: " + statustext + " : "  + error);
         fkey404 = true; // Could be other errors but we'll treat them the same
       }, 
       'method': 'POST', // We realy don't want this cached
       'success': function (data,statustext, xhr) {
         console.log("Found it!" + statustext + ": "  + data);
         statusok=data*1;
         if (statusok) checkStatus();
         setTimeout(checkStatusReady,500);
         // and call the ajax stuff.
       },
       'timeout': 3000
     });
   } else {
     setTimeout(checkStatus,500);
   }
}

 // Actually check order status by calling admin-ajax
 function checkStatus()  {
   console.log(vippsajaxurl);
   
//   setTimeout(checkStatus, 3000);
 }

 checkStatusReady();

});
