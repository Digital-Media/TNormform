<?php

namespace Fhooe\NormForm\View;

use Fhooe\NormForm\Parameter\GenericParameter;
use Fhooe\NormForm\Parameter\ParameterInterface;
use Fhooe\NormForm\Parameter\PostParameter;
use Twig\Environment;
use Twig\Error\LoaderError;
use Twig\Error\RuntimeError;
use Twig\Error\SyntaxError;
use Twig\Loader\FilesystemLoader;

/**
 * Encapsulates data for displaying a form or result of a form submission and uses the Twig template engine to render
 * its output.
 *
 * This class manages the parameters that are involved in the form (which should implement ParameterInterface) and
 * allows for a general PHP redirect. It initializes the Twig template engine and passes the stored parameters to it.
 * Twig is then used to render and display the form as specified in the main template. This view also passes on the
 * $_SERVER superglobal to the template (accessible as "_server"). Exceptions generated by Twig are shown as errors and
 * logged accordingly.
 *
 * @package Fhooe\NormForm\View
 * @author Wolfgang Hochleitner <wolfgang.hochleitner@fh-hagenberg.at>
 * @author Martin Harrer <martin.harrer@fh-hagenberg.at>
 * @author Rimbert Rudisch-Sommer <rimbert.rudisch-sommer@fh-hagenberg.at>
 * @version 1.0.0
 */
class View
{
    /**
     * The name of the view (the template file that is to be rendered).
     *
     * @var string
     */
    private $templateName;

    /**
     * The relative path to the directory where the template files are stored.
     *
     * @var string
     */
    private $templateDirectory;

    /**
     * The relative path where cached/compiled templates are to be stored.
     *
     * @var string
     */
    private $templateCacheDirectory;

    /**
     * An array of parameters used for display.
     *
     * @var array
     */
    private $params;

    /**
     * The Twig loader instance.
     *
     * @var FilesystemLoader
     */
    private $loader;

    /**
     * The main instance of the Twig template engine (environment).
     *
     * @var Environment
     */
    private $twig;

    /**
     * Creates a new view with the main template to be displayed, the path to the template and compiled templates
     * directory as well as parameters of the form. Also initializes the Twig template engine with caching and auto
     * reload enabled. Two global variables for $_SERVER and (if available) for $_SESSION are passed to the template for
     * easy access.
     * @param string $templateName The name of the template to be displayed.
     * @param string $templateDirectory The path where the template file is located (default is "templates").
     * @param string $templateCacheDirectory The path where cached template files are to be stored (default is
     * "templates_c").
     * @param array $params The parameters used when displaying the view.
     */
    public function __construct(
        string $templateName,
        string $templateDirectory = "templates",
        string $templateCacheDirectory = "templates_c",
        array $params = []
    ) {
        $this->templateName = $templateName;
        $this->templateDirectory = $templateDirectory;
        $this->templateCacheDirectory = $templateCacheDirectory;
        $this->params = $params;

        $this->loader = new FilesystemLoader($this->templateDirectory);
        $this->twig = new Environment($this->loader, [
            "cache" => $this->templateCacheDirectory,
            "auto_reload" => true
        ]);
        $this->twig->addGlobal("_server", $_SERVER);
        if (isset($_SESSION)) {
            $this->twig->addGlobal("_session", $_SESSION);
        }
    }

    /**
     * Returns the name of the main template that's being used for display.
     * @return string The template name.
     */
    public function getTemplateName(): string
    {
        return $this->templateName;
    }

    /**
     * Returns the supplied parameters.
     * @return array The parameters.
     */
    public function getParameters(): array
    {
        return $this->params;
    }

    /**
     * Allows to add or redefine parameters when the view object already exists. This avoids having to create a
     * completely new view object just because one parameter has changed or needs to be added. This method first checks
     * if a parameter with the given name is already stored within the view. If so, it updates its value with the one
     * supplied in $param. If the parameter is not present in the view, it is being added.
     * @param ParameterInterface $param The parameter to be added or updated.
     */
    public function setParameter(ParameterInterface $param): void
    {
        $paramName = $param->getName();
        foreach ($this->params as &$arg) {
            if ($arg->getName() === $paramName) {
                $arg = $param;
                return;
            }
        }
        $this->params[] = $param;
    }

    /**
     * Displays the current view. Iterates over all the parameters and stores them in a temporary, associative array.
     * Twig then displays the main template, using the array with the parameters.
     * Exceptions generated by Twig are shown as errors and logged accordingly for simplification.
     */
    public function display(): void
    {
        $templateParameters = [];
        foreach ($this->params as $param) {
            if ($param instanceof PostParameter) {
                $templateParameters[$param->getName()] = $param;
            } else {
                if ($param instanceof GenericParameter) {
                    $templateParameters[$param->getName()] = $param->getValue();
                }
            }
        }
        try {
            $this->twig->display($this->templateName, $templateParameters);
        } catch (LoaderError $e) {
            trigger_error($e->getMessage(), E_USER_ERROR);
            error_log($e->getMessage());
        } catch (RuntimeError $e) {
            trigger_error($e->getMessage(), E_USER_ERROR);
            error_log($e->getMessage());
        } catch (SyntaxError $e) {
            trigger_error($e->getMessage(), E_USER_ERROR);
            error_log($e->getMessage());
        }
    }

    /**
     * Performs a generic redirect using header(). GET-Parameters may optionally be supplied as an associative array.
     * @param string $location The target location for the redirect.
     * @param array $queryParameters GET-Parameters for HTTP-Request
     */
    public static function redirectTo(string $location, array $queryParameters = null): void
    {
        if (isset($queryParameters)) {
            header("Location: $location" . "?" . http_build_query($queryParameters));
        } else {
            header("Location: $location");
        }
        exit();
    }
}
