jQuery(document).ready(function ($) {
  $(document.body).on("click", "button#track-order-button", function () {
    let errorElement = "";
    const baseurl = document.getElementById("baseurl").value;
    const ps_merchant_id = document.getElementById("ps_merchant_id").value;
    const ps_password = document.getElementById("ps_password").value;
    const payment_url = document.getElementById("payment_url").value;
    const cartTotal = document.getElementById("cartTotal").value;
    const cust_name = document.getElementById("billing_first_name").value;
    const cust_phone = document.getElementById("billing_phone").value;
    const cust_email = document.getElementById("billing_email").value;
    const cust_address = document.getElementById("billing_address_1").value;
    const url = baseurl + "/wp-admin/admin-ajax.php";

    const obj = {
      merchantId: ps_merchant_id,
      password: ps_password,
    };

    // $.post(url, { action: "get_cart_items" }, function (response) {
    //   if (response.length) {
    //     apiCall(response);
    //   }
    // });

    const demo = $(".checkout").serializeArray();

  //  console.log("Data sent to server:", demo);


    $.post(url, { action: "complete_order", data: demo }, function (response) {
    //  console.log("AJAX Response:", response);

      if (response.order_id > 0 && response.returnURL) {
        makePayment(response.order_id, response.returnURL);
      } else {
        const message = "Failed to complete order";
        errorElement =
          "<br /><span style='color: #a94442;background-color: #f2dede;border-color: #ebccd1;padding: 15px;border: 1px solid transparent;border-radius: 4px;'>" +
          message +
          "</span>";
        $("#payment").append(errorElement);
      }
    });

    function makePayment(order_id, returnURL) {
      const body = {
        access: obj,
        cartTotal: cartTotal,
        cust_name: cust_name,
        cust_phone: cust_phone,
        cust_email: cust_email,
        cust_address: cust_address,
        invoice_number: order_id,
        baseurl: baseurl,
        returnURL: returnURL,
        // Add billing_ prefix to the keys
        billing_first_name: cust_name,
        billing_phone: cust_phone,
        billing_email: cust_email,
        billing_address_1: cust_address,
      };
      if (cartTotal > 0) {
        $.ajax({
          url: payment_url,
          data: body,
          method: "POST",
          dataType: "json",
          success: function (data) {
            console.log(data);
            if (data.status === "success") {
              window.open(data.payment_url, "_self");
            } else {
              $("button#track-order-button").attr("disabled", true);
              errorElement =
                "<br /><span style='color: #a94442;background-color: #f2dede;border-color: #ebccd1;padding: 15px;border: 1px solid transparent;border-radius: 4px;'>" +
                data.status +
                " - " +
                data.message +
                "</span>";
              $("#payment").append(errorElement);
            }
          },
        });
      }
    }
  });
  $(document.body).on("change", "input[name=payment_method]", function () {
    if (this.value == "paystation_payment_gateway") {
      $("#place_order").hide();
      $("#track-order-button").show();
    } else {
      $("#place_order").show();
      $("#track-order-button").hide();
    }
  });
});
