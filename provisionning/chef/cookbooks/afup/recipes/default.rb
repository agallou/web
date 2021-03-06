# Upgrade the server
# ¯¯¯¯¯¯¯¯¯¯¯¯¯¯¯¯¯¯
execute "apt-get update"
execute "apt-get -y upgrade"

# Apache
# ¯¯¯¯¯¯
package "apache2"

# Set the configuration
template "/etc/apache2/sites-available/00-afup.dev.conf" do
    source "virtualhost.erb"
end

directory '/etc/apache2/ssl' do
  owner 'root'
  group 'root'
  mode '0755'
  action :create
end

cookbook_file "apache.crt" do
    path "/etc/apache2/ssl/apache.crt"
    action :create_if_missing
    mode "0600"
    owner 'root'
    group 'root'
end

cookbook_file "apache.key" do
    path "/etc/apache2/ssl/apache.key"
    action :create_if_missing
    mode "0600"
    owner 'root'
    group 'root'
end

# Activate modules and site
execute "a2enmod rewrite expires headers ssl"
execute "a2dissite 000-default"
execute "a2ensite 00-afup.dev"

# PHP
# ¯¯¯
# Use dotdeb
cookbook_file "dotdeb.list" do
  path "/etc/apt/sources.list.d/dotdeb.list"
  action :create_if_missing
end
execute "wget http://www.dotdeb.org/dotdeb.gpg -O /tmp/dotdeb.gpg"
execute "apt-key add /tmp/dotdeb.gpg"
execute "apt-get update"

# Install PHP
package "php5-cli"
package "php5-common"
package "php5-mysqlnd"
package "php5-gd"
package "php5-mcrypt"
package "php5-curl"

# Install and activate the apache2 module
package "libapache2-mod-php5"
execute "a2enmod php5"

# Mariadb
# ¯¯¯¯¯¯¯
cookbook_file "mariadb.list" do
  path "/etc/apt/sources.list.d/mariadb.list"
  action :create_if_missing
end
package "software-properties-common"
execute "apt-key adv --recv-keys --keyserver keyserver.ubuntu.com 0xcbcb082a1bb943db"
execute "apt-get update"
package "mariadb-server"

# Set the root password
execute "setup root password" do
  command "mysqladmin -u root password #{node[:mysql][:root_password]}"
  action :run
  only_if "mysql -u root -e 'show databases;'"
end

# Create the database
execute "create database" do
    command "mysqladmin -uroot -p#{node[:mysql][:root_password]} create #{node[:mysql][:db_name]}"
    action :run
    only_if "test ! `mysql -uroot -p#{node[:mysql][:root_password]} -e 'use #{node[:mysql][:db_name]}' && echo $?`"
end

# Create the user
execute "setup user" do
    command "mysql -uroot -p#{node[:mysql][:root_password]} -e \"GRANT ALL ON *.* TO '#{node[:mysql][:user]}'@'localhost' IDENTIFIED BY '#{node[:mysql][:password]}' WITH GRANT OPTION;\""
    action :run
    only_if "test ! `mysql -u#{node[:mysql][:user]} -p#{node[:mysql][:password]} -e 'use #{node[:mysql][:db_name]}' && echo $?`"
end

# # Mailcatcher
# # ¯¯¯¯¯¯¯¯¯¯¯
# package "libsqlite3-dev"
# package "ruby2.1-dev"
# execute "gem install mailcatcher" do
#     command "gem install mailcatcher"
#     action :run
#     only_if "test ! `gem list mailcatcher -i > /dev/null && echo $?`"
# end
#
# # Use as a Service and start on boot
# cookbook_file "mailcatcher.initd" do
#     path "/etc/init.d/mailcatcher"
#     action :create_if_missing
#     mode "0755"
# end
# execute "update-rc.d mailcatcher defaults"


# Smarty Cache
# -----------
execute "mkdir -p /var/www/afup/htdocs/cache/templates && chmod 0777 /var/www/afup/htdocs/cache/templates"

# Creating default config file
# -----------
cookbook_file "config.php.dist" do
    path "/var/www/afup/configs/application/config.php"
    action :create_if_missing
    mode "0755"
end

# Restart services
# ¯¯¯¯¯¯¯¯¯¯¯¯¯¯¯¯¯¯¯¯¯¯¯¯
service "mysql" do
    action :restart
end
service "apache2" do
    action :restart
end
# service "mailcatcher" do
#     action :restart
# end

# Composer
# --------
execute "EXPECTED_SIGNATURE=$(wget -q -O - https://composer.github.io/installer.sig)"
execute "php -r \"copy('https://getcomposer.org/installer', 'composer-setup.php');\""
execute "ACTUAL_SIGNATURE=$(php -r \"echo hash_file('SHA384', 'composer-setup.php');\")"
execute "if [ \"$EXPECTED_SIGNATURE\" != \"$ACTUAL_SIGNATURE\" ]; then echo 'Installer corrupt'; rm composer-setup.php; fi"
execute "php composer-setup.php"
execute "php -r \"unlink('composer-setup.php');\""
execute "mv composer.phar /usr/local/bin/composer"
execute "install composer" do
    command "cd /var/www/afup/ && composer install"
    user "www-data"
    group "www-data"
end
