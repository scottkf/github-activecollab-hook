#!/usr/local/bin/ruby

require 'rubygems'
require 'vendor/sinatra/lib/sinatra.rb'
#require 'sinatra'

module Sinatra
  class Server
    def start
      begin
        log_fastcgi

        puts "== Sinatra has taken the stage via FastCGI!"
        tail_thread = tail(Options.log_file)

        Rack::Handler::FastCGI.run(Dispatcher.new) 
        self.class.running = true

        trap("INT") do
          self.class.running = false
          puts "\n== Sinatra has ended his FastCGI set"
        end
      rescue => e
        logger.exception e
      ensure
        tail_thread.kill if tail_thread
      end
    end

    def log_fastcgi
      fastcgi_log = File.open("fastcgi.log", "a")
      STDOUT.reopen fastcgi_log
      STDERR.reopen fastcgi_log
      STDOUT.sync = true
    end
  end
end

module Rack
  class Request
    def path_info
      @env["SCRIPT_URL"].to_s
    end
    def path_info=(s)
      @env["SCRIPT_URL"] = s.to_s
    end
  end
end

load './github-ac.rb'
