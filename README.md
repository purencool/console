**Console**

Console is a terminal application built on top of the [tui](https://github.com/php-tui/php-tui) PHP UI terminal library. It provides a simple and intuitive interface that integrates with other tools and applications to deliver a seamless experience for users.

**Tools**
### Coming soon...

**Installation**
To get started with Console, follow these steps:

1. Clone the repository using Git: `git clone https://github.com/purencool/console.git`
2. Navigate into the cloned directory: `cd console`
3. Copy the `.env.example` file to `.env`: `cp .env.example .env`
4. Install required dependencies using Composer: `composer install`
5. Start the application: `bash ./app.sh`

**Project Structure**
The Console project is organized into several directories:

* **Application**: Contains the main application class and related functionality.
* **contracts**: Defines interfaces for services and other components.
* **Services**: Provides various services that power the console, such as clipboard management, configuration management, and more.
* **State**: Manages the application's state, including app states, chat states, editor states, system states, and terminal states.
* **Support**: Contains utility classes for reflection, terminal setup, and other miscellaneous functionality.
* **UI**: Holds the user interface components, including event handlers, renderers, and more.

**Components**
Some key components of Console include:

* **ChatState**: Manages the chat state of the application.
* **EditorState**: Manages the editor state of the console.
* **TerminalLayoutService**: Provides a terminal layout service that arranges UI elements in a specific way.
* **Keybindings**: Handles keyboard input and bindings for commands.

**Getting Involved**n
We welcome contributions to Console! If you're interested in contributing, please see our [CONTRIBUTING.md](https://github.com/purencool/console/blob/main/CONTRIBUTING.md) file for more informatio