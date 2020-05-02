#!/bin/bash

echo -e "${Green}\n############################### Updating and Getting Ready #############################\n"
sudo apt update
sudo apt install -y git
cd ~
git clone https://github.com/arvage/OpenVPN-Admin openvpn-admin
cd openvpn-admin
sudo ./install.sh /var/www www-data www-data