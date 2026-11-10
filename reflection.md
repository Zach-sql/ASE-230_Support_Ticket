# Support Ticket Application Reflection

1. **Why does searching use GET while creating a ticket uses POST?**

   Searching only retrieves tickets, so GET lets the filters appear in the URL and makes a search easy to bookmark or share. Creating a ticket changes saved data, so the form uses POST and sends the values in the request body. After saving, the application redirects to the list so refreshing does not submit the ticket again.

2. **Why is server-side validation necessary if the HTML form performs validation?**

   Browser validation can be bypassed by changing the HTML or sending a request directly. PHP must check the name, email, category, description, and status before saving anything. It must also escape values when displaying them so user input is treated as text rather than HTML.

3. **What happens to an uploaded file before PHP moves it to the final upload directory?**

   PHP receives the form upload and places the file in a temporary location on the server. Information such as its temporary path, size, and upload error is available in `$_FILES`. The code checks the upload result, actual file size, MIME type, and image information before using `move_uploaded_file()` to give it a random filename in `uploads/`. A temporary file that is not moved is normally removed when the request ends.

4. **Where could you use a similar request-processing pattern in your capstone project?**

   In my Clipfarm video-sharing project, GET requests could search videos or display a video's details. POST requests could create accounts, submit comments, and upload videos. Each request would follow the same pattern: validate input, process the request, save or retrieve the data, and return a response or redirect.
