===========================
Sphinx Documentation Server
===========================

This document describes how to install a personal Sphinx documentation server.

Requirements
============

- `Ansible <http://docs.ansible.com/>`_. We use Ansible to easily deploy Sphinx and scripts to the server.
  The deployment receipes and scripts are found in this Git project.

- Linux server. We will use a blank `Debian AMD64 <https://www.debian.org/CD/netinst/>`_ virtual
  machine to start with. Installation was done with only a SSH server running and command :command:`sudo`
  being available.


Installing Ansible
------------------

Ansible is needed on a so-called "Control Machine"; that is, a computer that will control the Sphinx
server. This is typically your personal computer.

We will install Ansible from source since this is the recommended method. Just pick your preferred
user directory and::

    $ sudo easy_install pip
    $ sudo pip install paramiko PyYAML Jinja2 httplib2 six
    $ git clone git://github.com/ansible/ansible.git --recursive
    $ cd ./ansible
    $ source ./hacking/env-setup

Configuring Ansible
-------------------

Edit or create file :file:`/etc/ansible/hosts` and put a reference to your (blank) server::

    [sphinx]
    192.168.81.132    ansible_ssh_user=xavier

Provisioning Documentation server
---------------------------------

Run::

    $ git clone https://git.typo3.org/Teams/Documentation.git
    $ cd Documentation
    $ ansible-playbook -s install.yml

.. note::
    You may need to specify your password for :command:`sudo`, if Ansible fails with ``ERROR! Missing sudo password``.
    If so, you may ask to be prompted to give it like that::

	    $ ansible-playbook -s install.yml --ask-sudo-pass

Administrating Sphinx
---------------------

When logging onto your server, you are encouraged to work as user "sphinx" and follow the guide::

    $ sudo su - sphinx
