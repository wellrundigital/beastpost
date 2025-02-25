# BeastPost WordPress Plugin

BeastPost is a WordPress plugin that leverages OpenAI's models and the Pexels API to generate detailed, well-formatted blog posts based on a subject provided by the user. In addition, it automatically retrieves a featured image using the Pexels API to complement the generated content.

## Features

- **Dynamic Content Generation:**  
  Uses OpenAI's API to create a comprehensive blog post, complete with a title, headers, lists, citations, and more.
  
- **SEO Optimized Links:**  
  Automatically returns an SEO-friendly link based on the generated content.
  
- **Featured Image Integration:**  
  Uses a three-word image description provided by OpenAI to query Pexels and set a featured image for the post.
  
- **API Key Management:**  
  Displays graphical indicators for the availability of your OpenAI and Pexels API keys, with quick access to input missing keys directly from the post editor.
  
- **Easy to Use:**  
  Integrates seamlessly into the WordPress post/page editor with a dedicated BeastPost sidebar section.

## Installation

### Via the WordPress Dashboard (ZIP Upload):

1. **Download the Plugin:**  
   Zip the entire `beastpost` folder into a file named `beastpost.zip`.

2. **Upload the Plugin:**  
   - Log in to your WordPress admin dashboard.
   - Navigate to **Plugins > Add New**.
   - Click **Upload Plugin** and select the `beastpost.zip` file.
   - Click **Install Now** and then **Activate Plugin**.

### Via FTP:

1. **Extract and Upload:**  
   - Extract the plugin files if they are archived.
   - Upload the entire `beastpost` folder to the `/wp-content/plugins/` directory on your server using an FTP client.

2. **Activate the Plugin:**  
   - Log in to your WordPress admin dashboard.
   - Navigate to **Plugins** and activate **BeastPost**.

## Usage

1. **Configure API Keys:**  
   In the post or page editor, locate the **BeastPost** sidebar section.  
   - If your **OpenAI API key** or **Pexels API key** is missing, click the corresponding link to input and save your key.

2. **Generate a Post:**  
   - Enter the subject of your article in the main input field.
   - Click the **Create Post** button.
   - The plugin will use the subject to request a detailed blog post from OpenAI and then update your post’s content, SEO link, and featured image automatically.

## Contributing

Contributions are welcome! If you have suggestions, bug fixes, or new features, please feel free to fork the repository and submit a pull request. For major changes, please open an issue to discuss your ideas first.

## License

This project is licensed under the [MIT License](LICENSE).

## Acknowledgements

- [OpenAI](https://openai.com) for their powerful API services.
- [Pexels](https://www.pexels.com) for providing high-quality free images.
- The WordPress community for making plugin development accessible and engaging.
