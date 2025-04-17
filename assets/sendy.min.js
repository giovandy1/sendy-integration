jQuery(document).ready(function($) {
    // Function to handle database errors that might appear outside our control
    function removeWordPressErrors() {
        $('div#error').remove();
    }
    
    // Run this immediately to remove any errors that might be present on page load
    removeWordPressErrors();
    
    // Subscribe form submission
    $("#custom-sendy-form").on("submit", function(e) {
        e.preventDefault();
        
        // More specific selector for the submit button
        let submitButton = $(this).find('input[type="submit"], button[type="submit"]');
        
        // Check if button exists and store its original text
        let originalText = submitButton.val() || submitButton.text();
        
        // Change button text/value and disable it
        if (submitButton.is("input")) {
            submitButton.val("Loading...").prop("disabled", true);
        } else {
            submitButton.text("Loading...").prop("disabled", true);
        }
        
        let formData = {
            action: "custom_sendy_subscribe",
            name: $("#custom_name").val(),
            email: $("#custom_email").val(),
            hp: $("#custom_hp").val()
        };
        
        // Use proper error handling for the AJAX request
        $.ajax({
            type: "POST",
            url: sendy_ajax.ajax_url,
            data: formData,
            success: function(response) {
                // Remove any WordPress error divs that might have been added to the page
                removeWordPressErrors();
                
                // Check if response contains HTML (error message)
                if (typeof response === 'string' && response.includes('<div id="error">')) {
                    // Extract the JSON part if it exists
                    let jsonMatch = response.match(/(\{.*\})$/);
                    if (jsonMatch && jsonMatch[1]) {
                        try {
                            response = JSON.parse(jsonMatch[1]);
                            // Continue with the parsed JSON
                            if (response.success) {
                                $("#custom-sendy-message").html('<span style="color:green;background-color:chartreuse;">' + response.data + '</span>');
                            } else {
                                $("#custom-sendy-message").html('<span style="color:red;">' + response.data + '</span>');
                            }
                        } catch (e) {
                            // JSON parsing failed
                            console.log("JSON parsing error:", e);
                            $("#custom-sendy-message").html('<span style="color:red;">Error processing response.</span>');
                        }
                    } else {
                        // No JSON found in the response
                        $("#custom-sendy-message").html('<span style="color:red;">Error processing response.</span>');
                    }
                } else if (typeof response === 'string') {
                    // Response is string but not HTML error - try to parse as JSON
                    try {
                        response = JSON.parse(response);
                        if (response.success) {
                            $("#custom-sendy-message").html('<span style="color:green;background-color:chartreuse;">' + response.data + '</span>');
                        } else {
                            $("#custom-sendy-message").html('<span style="color:red;">' + response.data + '</span>');
                        }
                    } catch (e) {
                        console.log("Response parsing error:", e);
                        $("#custom-sendy-message").html('<span style="color:red;">Error processing response.</span>');
                    }
                } else {
                    // Normal JSON response object
                    if (response.success) {
                        $("#custom-sendy-message").html('<span style="color:green;background-color:chartreuse;">' + response.data + '</span>');
                    } else {
                        $("#custom-sendy-message").html('<span style="color:red;">' + response.data + '</span>');
                    }
                }
            },
            error: function(xhr, status, error) {
                console.log("AJAX Error: " + status + " - " + error);
                $("#custom-sendy-message").html('<span style="color:red;">Connection error. Please try again.</span>');
            },
            complete: function() {
                // Always restore button regardless of success or failure
                if (submitButton.is("input")) {
                    submitButton.val(originalText).prop("disabled", false);
                } else {
                    submitButton.text(originalText).prop("disabled", false);
                }
                
                // Remove any WordPress error divs again after the response is processed
                removeWordPressErrors();
            }
        });
    });
    
    // Unsubscribe form submission
    $("#custom-sendy-unsub-form").on("submit", function(e) {
        e.preventDefault();
        
        // Get unsubscribe reason values
        let reason = $("#unsub_reason").val(),
            otherReason = $("#other_reason").val();
        
        if ("Other" === reason && !otherReason) {
            return void alert("Please provide a reason for unsubscribing.");
        }
        
        // More specific selector for the unsubscribe submit button
        let unsubButton = $(this).find('input[type="submit"], button[type="submit"]');
        
        // Check if button exists and store its original text
        let originalUnsubText = unsubButton.val() || unsubButton.text();
        
        // Change button text/value and disable it
        if (unsubButton.is("input")) {
            unsubButton.val("Loading...").prop("disabled", true);
        } else {
            unsubButton.text("Loading...").prop("disabled", true);
        }
        
        let unsubData = {
            action: "custom_sendy_unsubscribe",
            email: $("#unsub_email").val(),
            reason: reason,
            other_reason: otherReason,
            hp: $("#unsub_hp").val()
        };
        
        // Use proper error handling for the AJAX request
        $.ajax({
            type: "POST",
            url: sendy_ajax.ajax_url,
            data: unsubData,
            success: function(response) {
                // Remove any WordPress error divs that might have been added to the page
                removeWordPressErrors();
                
                // Check if response contains HTML (error message)
                if (typeof response === 'string' && response.includes('<div id="error">')) {
                    // Extract the JSON part if it exists
                    let jsonMatch = response.match(/(\{.*\})$/);
                    if (jsonMatch && jsonMatch[1]) {
                        try {
                            response = JSON.parse(jsonMatch[1]);
                            // Continue with the parsed JSON
                            if (response.success) {
                                $("#custom-sendy-unsub-message").html('<span style="color:green;background-color:chartreuse;">' + response.data + '</span>');
                            } else {
                                $("#custom-sendy-unsub-message").html('<span style="color:red;">' + response.data + '</span>');
                            }
                        } catch (e) {
                            // JSON parsing failed
                            console.log("JSON parsing error:", e);
                            $("#custom-sendy-unsub-message").html('<span style="color:red;">Error processing response.</span>');
                        }
                    } else {
                        // No JSON found in the response
                        $("#custom-sendy-unsub-message").html('<span style="color:red;">Error processing response.</span>');
                    }
                } else if (typeof response === 'string') {
                    // Response is string but not HTML error - try to parse as JSON
                    try {
                        response = JSON.parse(response);
                        if (response.success) {
                            $("#custom-sendy-unsub-message").html('<span style="color:green;background-color:chartreuse;">' + response.data + '</span>');
                        } else {
                            $("#custom-sendy-unsub-message").html('<span style="color:red;">' + response.data + '</span>');
                        }
                    } catch (e) {
                        console.log("Response parsing error:", e);
                        $("#custom-sendy-unsub-message").html('<span style="color:red;">Error processing response.</span>');
                    }
                } else {
                    // Normal JSON response object
                    if (response.success) {
                        $("#custom-sendy-unsub-message").html('<span style="color:green;background-color:chartreuse;">' + response.data + '</span>');
                    } else {
                        $("#custom-sendy-unsub-message").html('<span style="color:red;">' + response.data + '</span>');
                    }
                }
            },
            error: function(xhr, status, error) {
                console.log("AJAX Error: " + status + " - " + error);
                $("#custom-sendy-unsub-message").html('<span style="color:red;">Connection error. Please try again.</span>');
            },
            complete: function() {
                // Always restore button regardless of success or failure
                if (unsubButton.is("input")) {
                    unsubButton.val(originalUnsubText).prop("disabled", false);
                } else {
                    unsubButton.text(originalUnsubText).prop("disabled", false);
                }
                
                // Remove any WordPress error divs again after the response is processed
                removeWordPressErrors();
            }
        });
    });
    
    // Handle "Other" reason selection
    $("#unsub_reason").change(function() {
        "Other" === $(this).val() ? 
            $("#custom_unsub_other_reason_wrap").show() : 
            $("#custom_unsub_other_reason_wrap").hide();
    });
    
    // Set up a periodic check to remove WordPress errors
    setInterval(removeWordPressErrors, 1000);
});

jQuery(document).ready(function($) {
    // Check all functionality
    $('#cb-select-all-1, #cb-select-all-2').click(function() {
        $('input[name="log_ids[]"]').prop('checked', $(this).prop('checked'));
    });
    
    // Confirm bulk delete
    $('#doaction').click(function(e) {
        if ($('#bulk-action-selector-top').val() === 'delete') {
            if (!$('input[name="log_ids[]"]:checked').length) {
                alert('Please select at least one item to delete.');
                e.preventDefault();
                return false;
            }
            
            if (!confirm('Are you sure you want to delete the selected log entries?')) {
                e.preventDefault();
                return false;
            }
        }
    });
});