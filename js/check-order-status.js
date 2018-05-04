// IOK 2018-05-04 Call ajax methods determining if and order is complete or not from the "confirm" waiting screen
jQuery(document).ready(function () {
 var start = new Date();
 var fkey = jQuery("#fkey").val();
 var fkey404 = false;
 var iterate = 0;
 var statusok = 0;
 var done=0;
 var aminute = 1 * 60 * 60 * 1000;
 var data  = jQuery("#vippsdata").serialize();

 function checkStatusReady() {
   console.log("Checking status");
   var now = new Date();
   iterate++;
   // If we have a fkey that hasn"t responed 404 yet and a minute hasn"t passed,  call that directly and often IOK 2018-05-04
   if (!statusok && fkey && !fkey404 && (now.getTime() - start.getTime()) < aminute) {
     // Use post to hide data slightly and to avoid caches
     var url = fkey + "?v="+iterate;
     jQuery.ajax(url,
      {"cache":false,
       "complete": function () { if (!statusok) setTimeout(checkStatusReady,500); },
       "dataType":"json",
       "error": function (xhr, statustext, error) {
         console.log("Error checking status: " + statustext + " : "  + error);
         fkey404 = true; // Could be other errors but we"ll treat them the same
       }, 
       "method": "POST", // We realy don"t want this cached
       "success": function (data,statustext, xhr) {
         console.log("Found it!" + statustext + ": "  + data);
         statusok=data*1;
         if (!statusok) {
           setTimeout(checkStatus,500);
         } else {
           setTimeout(checkStatusReady,500);
         }
       },
       "timeout": 3000
     });
   } else {
     statusok=1;
     setTimeout(checkStatus,500);
   }
};

 // Actually check order status by calling admin-ajax
 function checkStatus()  {
   jQuery.ajax(vippsajaxurl, {
    "method": "POST",
    "data":data,
    "cache":false,
    "complete": function () { if (!done) {console.log("Not done yet"); setTimeout(checkStatus,3000); } },
    "dataType": "json",
    "error": function (xhr, statustext, error) {
      console.log("Error checking order status " + statustext + " " + error);
      done=1;
      jQuery("#success").hide();
      jQuery("#failure").hide();
      jQuery("#error").show();
    },
    "success": function (result, statustext, xhr) {
     if (result["status"] == "waiting") {
       console.log("Waiting reuslt result");
       console.log("Waiting for Vipps callback..");
       // Do some update here.
       done=0;
     } else if (result["status"] == "ok") {
       console.log("Success result");
       done=1;
       setTimeout(function () {
         console.log("Redirecting!");
//         window.location.href = jQuery("#continue").attr("href");
       }, 1000);
       jQuery("#success").show(); 
       jQuery("#failure").hide();
       jQuery("#error").hide();
     } else if (result["status"]=="failed") {
       console.log("Failure result");
       done=1;
       jQuery("#success").hide(); 
       jQuery("#failure").show();
       jQuery("#error").hide();
     } else {
       console.log("Error result");
       done=1;
       jQuery("#success").hide(); 
       jQuery("#failure").hide();
       jQuery("#error").hide();
     }
    },
    "timeout": 0
   });
 }; 
   
   

 checkStatusReady();

});
