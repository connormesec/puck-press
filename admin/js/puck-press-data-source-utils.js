(function ($) {
    jQuery(document).ready(function ($) {

        window.PPDataSourceUtils = {
            handleFormSubmit: function (config) {
                const {
                    $form,
                    fieldExtractors,
                    beforeSubmit,
                    onSuccess,
                    onError,
                    action
                } = config;

                    if (!$form[0].checkValidity()) {
                        $form[0].reportValidity();
                        return;
                    }

                    const formData = new FormData();
                    formData.append('action', action);

                    try {
                        const extracted = fieldExtractors();
                        for (const [key, value] of Object.entries(extracted)) {
                            formData.append(key, value);
                        }
                    } catch (e) {
                        console.error(e);
                        alert(e.message || 'Form error.');
                        return;
                    }

                    if (beforeSubmit && beforeSubmit(formData) === false) {
                        return;
                    }

                    $.ajax({
                        url: ajaxurl,
                        method: 'POST',
                        data: formData,
                        processData: false,
                        contentType: false,
                        success: function (response) {
                            if (response.success) {
                                onSuccess && onSuccess(response);
                            } else {
                                console.error('Error:', response);
                                alert('Failed to submit.');
                                onError && onError(response);
                            }
                        },
                        error: function (err) {
                            console.error('Error:', err);
                            alert('Failed to submit.');
                            onError && onError(err);
                        }
                    });
            }
        };
    });
})(jQuery);