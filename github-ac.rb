#!/usr/local/bin/ruby

require 'rubygems'
require 'json'
require 'vendor/sinatra/lib/sinatra.rb'
require 'yaml'


get '' do
end

post '' do
  payload = JSON.parse(params[:payload])
  GithubActiveCollab.new(payload)
end


##
# This class does all of the json parsing and submits a push's commits to activeCollab
class GithubActiveCollab
  
  def initialize(payload)
    config = YAML.load_file('config.yml')

    
    payload['commits'].each do |c|
      process_commit('1', '2', payload['before'], config['submit_url'], config['curl'], config['token'])
    end
    
  end
  
  def process_commit(sha1, commit, before, submit_url, curl_path, token)
    
    # from each commit in the payload, we need to extract:
    # - name of repo, renamed as "github-<repo>"
    # - name of file, including branch. e.g.: "4.7/Builds/Cablecast.fbp4"
    # - sha1 of commit (R2)
    # - sha1 of before (R1)
    # - bugzid (found inside the commit message)
    
    message = commit["message"]
    files = commit["removed"] | commit["added"] | commit["modified"]
    
    # look for a bug id in each line of the commit message
    # bug_list = []
    # message.split("\n").each do |line|
    #  if (line =~ /\s*Bug[zs]*\s*IDs*\s*[#:; ]+((\d+[ ,:;#]*)+)/i)
    #    bug_list << $1.to_i
    #  end
    # end
    
    url = "#{submit_url}?token=#{token}"
    post = "submitted=submitted&ticket[name]=#{commit}&ticket[body]=#{message}#{files}"
    curl = "#{curl_path} -d #{post} -X POST -H \"Accept:application/json\" #{url}"
    puts "#{curl}"
    #`#{curl}`
    # puts `#{curl}`
    # # for each found bugzid, submit the files to fogbugz.
    # # this will set the sRepo to "github-<repo>", which will be used above
    # # when fogbugz asks for the scm viewer url.
    # bug_list.each do |fb_bugzid|
    #   files.each do |f|
    #     fb_repo = CGI.escape("github-#{repo}")
    #     fb_r1 = CGI.escape("#{before}")
    #     fb_r2 = CGI.escape("#{sha1}")
    #     fb_file = CGI.escape("#{branch}/#{f}")
    #     
    #     #build the GET request, and send it to fogbugz
    #     fb_url = "#{fb_submit_url}?ixBug=#{fb_bugzid}&sRepo=#{fb_repo}&sFile=#{fb_file}&sPrev=#{fb_r1}&sNew=#{fb_r2}"
    #     puts `#{curl_path} --insecure --silent --output /dev/null '#{fb_url}'`
    #  
    #   end
    # end
  end
end






