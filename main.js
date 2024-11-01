document.querySelectorAll('.wprc-form').forEach(function (form) {
    form.addEventListener('submit', async function (event) {
        event.preventDefault();
        const nameField = form.querySelector('[name="name"]');
        const nameErrors = nameField.parentElement.nextElementSibling;
        nameErrors.innerHTML = '';

        const phoneField = form.querySelector('[name="phone"]');
        const phoneErrors = phoneField.parentElement.nextElementSibling;
        phoneErrors.innerHTML = '';

        var error = form.parentNode.querySelector('.wprc-error-message');
        error.style.display = 'none';

        var success = form.parentNode.querySelector('.wprc-success-message');
        success.style.display = 'none';

        var xmlhttp = new XMLHttpRequest();
        xmlhttp.open('POST', wprcSettings.route);
        xmlhttp.setRequestHeader('Content-Type', 'application/json;charset=UTF-8');
        xmlhttp.responseType = 'json';
        xmlhttp.send(JSON.stringify({name: nameField.value, phone: phoneField.value}));
        console.log(xmlhttp);

        function displayError() {
            error.style.display = 'block';
        }

        xmlhttp.onload = function () {
            if (xmlhttp.status === 201) {
                form.parentNode.removeChild(form);
                success.style.display = 'block';
                return;
            }

            if (xmlhttp.status === 422) {
                function displayErrors(node, errors) {
                    let list = document.createElement('ul');
                    node.appendChild(list);
                    errors.forEach(function (error) {
                        let listItem = document.createElement('li');
                        listItem.innerText = error;
                        list.appendChild(listItem);
                    });
                }

                const data = xmlhttp.response;

                if (data.errors.name) {
                    displayErrors(nameErrors, data.errors.name);
                }

                if (data.errors.phone) {
                    displayErrors(phoneErrors, data.errors.phone);
                }

                return;
            }

            displayError();
        };

        xmlhttp.onerror = displayError;
    });
});
