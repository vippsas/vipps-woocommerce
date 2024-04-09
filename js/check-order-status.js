/*

This file is part of the plugin Pay with Vipps and MobilePay for WooCommerce
Copyright (c) 2019 WP-Hosting AS

MIT License

Copyright (c) 2019 WP-Hosting AS

Permission is hereby granted, free of charge, to any person obtaining a copy
of this software and associated documentation files (the "Software"), to deal
in the Software without restriction, including without limitation the rights
to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
copies of the Software, and to permit persons to whom the Software is
furnished to do so, subject to the following conditions:

The above copyright notice and this permission notice shall be included in all
copies or substantial portions of the Software.

THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE
SOFTWARE.
*/

// IOK 2018-05-04 Call ajax methods determining if and order is complete or not from the "confirm" waiting screen
jQuery(document).ready(function () {
 var start = new Date();
 var fkey = jQuery("#fkey").val();
 var fkey404 = false;
 var iterate = 0;
 var statusok = 0;
 var done=0;
 var halfminute = 1 * 30 * 1000;
 var data  = jQuery("#vippsdata").serialize();

 function checkStatusReady() {
   var now = new Date();
   iterate++;
   // If we have a fkey that hasn't responed 404 yet and a half a minute hasn't passed,  call that directly and often IOK 2018-05-04
   if (!statusok && fkey && !fkey404 && (now.getTime() - start.getTime()) < halfminute) {
     // Use post to hide data slightly and to avoid caches
     var url = fkey + "?v="+iterate;
     jQuery.ajax(url,
      {"cache":false,
       "headers": {
          'Cache-Control': 'max-age=0, must-revalidate, no-cache, no-store' 
       },
       "dataType":"json",
       "error": function (xhr, statustext, error) {
         console.log("Error checking status: " + statustext + " : "  + error);
         if (error == 'timeout')  {
          return setTimeout(checkStatusReady,500);
         }
         // Could be other errors but we"ll treat them the same - assume that we can't find the signal and call the ajax method
         fkey404 = true;
         statusok= true;
         setTimeout(checkStatus,500);
       }, 
       "method": "GET", // We must use GET because nginx will return 405 for POST to static files
       "success": function (result,statustext, xhr) {
         statusok=result*1;
         if (!statusok) {
           // No result yet, check often as this is cheap
           setTimeout(checkStatusReady,500);
         } else {
           // We have a result, so check what it is
           setTimeout(checkStatus,500);
         }
       },
       "timeout": 3000
     });
   } else {
     // This happens when we've waited for a minute or moe, if we don't have a key and so forth.
     statusok=1;
     setTimeout(checkStatus,500);
   }
};

 // Actually check order status by calling admin-ajax
 function checkStatus()  {
   jQuery.ajax(VippsConfig['vippsajaxurl'], {
    "method": "POST",
    "data":data,
    "cache":false,
    "dataType": "json",
    "error": function (xhr, statustext, error) {
       console.log("Error checking order status " + statustext + " " + error);
       done=1;
       var errorhandler = function (statustext, error) { 
                           jQuery("#waiting").hide();
                           jQuery("#success").hide();
                           jQuery("#failure").hide();
                           jQuery("#error").show();
                          };

       if (typeof wp !== 'undefined' && typeof wp.hooks !== 'undefined') {
         errorhandler = wp.hooks.applyFilters('vippsStatusCheckErrorHandler', errorhandler);
       }
       return errorhandler(statustext, error); 
      },

    "success": function (result, statustext, xhr) {
     if (result["status"] == "waiting") {
       console.log("Waiting for Vipps callback..");
       // Do some update here.
       done=0;
       setTimeout(checkStatus,3000);
     } else if (result["status"] == "ok") {
       console.log("Success result");
       done=1;
       setTimeout(function () {
         var next = jQuery("#continueToThankYou").attr("href");
         console.log("Redirecting to  " +next );
         window.location.href = next;
       }, 500);
      jQuery("#waiting").hide();
       jQuery("#success").show(); 
       jQuery("#failure").hide();
       jQuery("#error").hide();
     } else if (result["status"]=="failed") {
       console.log("Failure result");
       done=1;

       // If provided, continue to custom 'order failed' page IOK 2018-12-04
       var next = jQuery("#continueToOrderFailed").attr("href");
       if (next != '') {
          setTimeout(function () {
             console.log("Redirecting to  " +next );
             window.location.href = next;
          }, 500);
       }

      jQuery("#waiting").hide();
       jQuery("#success").hide(); 
       jQuery("#failure").show();
       jQuery("#error").hide();
     } else {
       console.log("Error result %j",result);
       done=1;
       var errorhandler = function (statustext, error) { 
                           jQuery("#waiting").hide();
                           jQuery("#success").hide();
                           jQuery("#failure").hide();
                           jQuery("#error").show();
                          };

       if (typeof wp !== 'undefined' && typeof wp.hooks !== 'undefined') {
         errorhandler = wp.hooks.applyFilters('vippsStatusCheckErrorHandler', errorhandler);
       }
       return errorhandler("Unknown result from WooCommerce", result);
     }
    },
    "timeout": 0
   });
 }; 
   
   

 checkStatusReady();

});
